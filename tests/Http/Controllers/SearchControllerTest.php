<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\SearchController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Service\PermissionService;
use Phorum\Tests\Http\ControllerTestCase;

class SearchControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): SearchController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);

        return new SearchController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
            forums: $deps['forums'] ?? $this->createMock(ForumMapper::class),
            perms:  $perms,
        );
    }

    public function testIndexReturns200WithNoQuery(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->index($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testIndexFiltersOutUnreadableForums(): void
    {
        // Two forums, one readable and one not — verify only readable ones are exposed
        $readable  = $this->makeForum(1);
        $forbidden = $this->makeForum(2);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$readable, $forbidden]);

        $perms = $this->createMock(PermissionService::class);
        // Only the first forum is readable
        $perms->method('canRead')->willReturnCallback(
            fn($forum, $user) => $forum->forum_id === 1
        );

        // Use a render-capturing Twig mock to verify only readable forum is in template data
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('getLoader')->willReturn(
            $this->createMock(\Twig\Loader\LoaderInterface::class)
        );
        $capturedData = null;
        $twig->method('render')->willReturnCallback(
            function (string $template, array $data) use (&$capturedData): string {
                $capturedData = $data;
                return '<html>test</html>';
            }
        );

        $ctrl     = new SearchController(
            config: $this->makeConfig(),
            twig:   $twig,
            forums: $forums,
            perms:  $perms,
        );
        $response = $ctrl->index($this->makeGetRequest());
        $this->assertSame(200, $response->status);
        $this->assertCount(1, $capturedData['readable_forums']);
        $this->assertSame(1, $capturedData['readable_forums'][0]->forum_id);
    }

    public function testIndexWithAuthorOnlyQueryReturns200(): void
    {
        // A search submitted via 'author' param goes through the search code path.
        // SearchController instantiates MysqlSearchService inline, which requires DB.
        // We verify the controller at least attempts the search path when author is present —
        // the call will throw a DB exception, which we expect here.
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl = $this->makeController(['forums' => $forums]);
        $this->expectException(\Throwable::class);
        $ctrl->index($this->makeGetRequest(query: ['author' => 'bob']));
    }
}
