<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Http\Controllers\LegacyRedirectController;
use Phorum\Http\Request;
use Phorum\Tests\Http\ControllerTestCase;

class LegacyRedirectControllerTest extends ControllerTestCase
{
    private function makeController(): LegacyRedirectController
    {
        return new LegacyRedirectController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
        );
    }

    public function testIndexRedirectsNumberedForumIdToForumUrl(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request(server: ['QUERY_STRING' => '23']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/23', $response->headers['Location']);
    }

    public function testIndexRedirectsBareRequestToRoot(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request(server: ['QUERY_STRING' => '']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }
}
