<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Service\DiffRenderer;
use PHPUnit\Framework\TestCase;

class DiffRendererTest extends TestCase
{
    private DiffRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new DiffRenderer();
    }

    public function testIdenticalStringsReturnsEscapedInput(): void
    {
        $result = $this->renderer->renderHtml('hello world', 'hello world');
        $this->assertSame('hello world', $result);
    }

    public function testIdenticalStringsWithHtmlEntitiesAreEscaped(): void
    {
        $result = $this->renderer->renderHtml('<b>hi</b>', '<b>hi</b>');
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', $result);
    }

    public function testChangedContentContainsInsAndDelTags(): void
    {
        $result = $this->renderer->renderHtml("old line\n", "new line\n");
        $this->assertStringContainsString('<del>', $result);
        $this->assertStringContainsString('<ins>', $result);
    }

    public function testAddedContentIsWrappedInIns(): void
    {
        $result = $this->renderer->renderHtml('', "added\n");
        $this->assertStringContainsString('<ins>', $result);
        $this->assertStringNotContainsString('<del>', $result);
    }

    public function testRemovedContentIsWrappedInDel(): void
    {
        $result = $this->renderer->renderHtml("removed\n", '');
        $this->assertStringContainsString('<del>', $result);
        $this->assertStringNotContainsString('<ins>', $result);
    }

    public function testHtmlSpecialCharsInChangedTextAreEscaped(): void
    {
        $result = $this->renderer->renderHtml('plain', '<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testEmptyOldAndNewReturnsEmptyString(): void
    {
        $result = $this->renderer->renderHtml('', '');
        $this->assertSame('', $result);
    }

    public function testResultDoesNotContainRawScriptTag(): void
    {
        $result = $this->renderer->renderHtml('a', '<script>xss</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }
}
