<?php
declare(strict_types=1);

namespace Phorum\Tests\Twig;

use DealNews\SchemaOrg\Type\WebPage;
use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Twig\PhorumExtension;
use PHPUnit\Framework\TestCase;

class PhorumExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeExt(): PhorumExtension
    {
        return new PhorumExtension();
    }

    // -------------------------------------------------------------------------
    // formatDatestamp
    // -------------------------------------------------------------------------

    public function testFormatDatestampReturnsEmDashForZero(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('&mdash;', $ext->formatDatestamp(0));
    }

    public function testFormatDatestampFormatsTimestamp(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->formatDatestamp(strtotime('2024-01-15 10:00:00'));
        $this->assertStringContainsString('2024', $result);
    }

    public function testFormatDatestampUsesCustomFormat(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->formatDatestamp(strtotime('2024-01-15'), 'Y-m-d');
        $this->assertSame('2024-01-15', $result);
    }

    // -------------------------------------------------------------------------
    // relativeTime
    // -------------------------------------------------------------------------

    public function testRelativeTimeReturnsNeverForZero(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('never', $ext->relativeTime(0));
    }

    public function testRelativeTimeReturnsJustNow(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('just now', $ext->relativeTime(time() - 10));
    }

    public function testRelativeTimeReturnsMinutesAgo(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->relativeTime(time() - 90);
        $this->assertStringEndsWith('m ago', $result);
    }

    public function testRelativeTimeReturnsHoursAgo(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->relativeTime(time() - 7200);
        $this->assertStringEndsWith('h ago', $result);
    }

    public function testRelativeTimeReturnsDaysAgo(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->relativeTime(time() - 172800); // 2 days
        $this->assertStringEndsWith('d ago', $result);
    }

    public function testRelativeTimeReturnsDateForOldTimestamp(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->relativeTime(strtotime('2020-01-01'));
        $this->assertStringContainsString('2020', $result);
    }

    // -------------------------------------------------------------------------
    // paginationUrl
    // -------------------------------------------------------------------------

    public function testPaginationUrlReturnsBaseForPageOne(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('/forum/1', $ext->paginationUrl('/forum/1', 1));
    }

    public function testPaginationUrlAppendsQueryParamForOtherPages(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('/forum/1?page=3', $ext->paginationUrl('/forum/1', 3));
    }

    // -------------------------------------------------------------------------
    // absoluteUrl
    // -------------------------------------------------------------------------

    public function testAbsoluteUrlPrefixesConfiguredBaseUrl(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'base_url' ? 'https://myforum.example.com' : $default
        );

        $ext = new PhorumExtension($config);
        $this->assertSame('https://myforum.example.com/forum/1?page=3', $ext->absoluteUrl('/forum/1?page=3'));
    }

    public function testAbsoluteUrlStripsTrailingSlashFromBaseUrl(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'base_url' ? 'https://myforum.example.com/' : $default
        );

        $ext = new PhorumExtension($config);
        $this->assertSame('https://myforum.example.com/forum/1', $ext->absoluteUrl('/forum/1'));
    }

    public function testAbsoluteUrlReturnsBarePathWithoutConfig(): void
    {
        $ext = $this->makeExt();
        $this->assertSame('/forum/1', $ext->absoluteUrl('/forum/1'));
    }

    // -------------------------------------------------------------------------
    // paginationRange
    // -------------------------------------------------------------------------

    public function testPaginationRangeReturnsEmptyForSinglePage(): void
    {
        $ext = $this->makeExt();
        $this->assertSame([], $ext->paginationRange(1, 1));
    }

    public function testPaginationRangeReturnsAllPagesWhenNoGapExists(): void
    {
        $ext = $this->makeExt();
        $this->assertSame([1, 2, 3, 4, 5], $ext->paginationRange(3, 5));
    }

    public function testPaginationRangeFillsInSingleSkippedPageInsteadOfEllipsis(): void
    {
        $ext = $this->makeExt();
        // Window (1..3) plus last page (5) leaves only page 4 skipped —
        // too small a gap to bother with an ellipsis for.
        $this->assertSame([1, 2, 3, 4, 5], $ext->paginationRange(1, 5));
        $this->assertSame([1, 2, 3, 4, 5], $ext->paginationRange(5, 5));
    }

    public function testPaginationRangeCollapsesMiddleOfLargeRangeAroundCurrentPage(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->paginationRange(50, 1000);
        $this->assertSame([1, null, 47, 48, 49, 50, 51, 52, 53, null, 1000], $result);
    }

    public function testPaginationRangeCollapsesTailWhenCurrentPageIsNearStart(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->paginationRange(1, 1000);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, null, 1000], $result);
    }

    public function testPaginationRangeCollapsesHeadWhenCurrentPageIsNearEnd(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->paginationRange(1000, 1000);
        $this->assertSame([1, null, 993, 994, 995, 996, 997, 998, 999, 1000], $result);
    }

    public function testPaginationRangeExpandsWindowUntilMinVisibleIsMet(): void
    {
        // With just 12 pages, the default window alone (~5 entries) falls
        // well short of the 10-entry minimum — it should keep widening.
        $ext    = $this->makeExt();
        $result = $ext->paginationRange(1, 12);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, null, 12], $result);
        $this->assertCount(10, $result);
    }

    public function testPaginationRangeHonorsCustomMinVisible(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->paginationRange(50, 1000, 1);
        $this->assertSame([1, null, 49, 50, 51, null, 1000], $result);
    }

    // -------------------------------------------------------------------------
    // renderMarkdown
    // -------------------------------------------------------------------------

    public function testRenderMarkdownConvertsToHtml(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->renderMarkdown('**bold**');
        $this->assertStringContainsString('<strong>bold</strong>', $result);
    }

    public function testRenderMarkdownStripsUnsafeHtml(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->renderMarkdown('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderMarkdownAddsNofollowToExternalLinks(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->renderMarkdown('[external](https://evil.example.com/x)');
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    public function testRenderMarkdownLeavesRelativeLinksAlone(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->renderMarkdown('[local](/thread/1)');
        $this->assertStringNotContainsString('rel=', $result);
        $this->assertStringNotContainsString('target=', $result);
    }

    public function testRenderMarkdownTreatsConfiguredBaseUrlHostAsInternal(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'base_url' ? 'https://myforum.example.com' : $default
        );

        $ext    = new PhorumExtension($config);
        $result = $ext->renderMarkdown('[same site](https://myforum.example.com/thread/1)');
        $this->assertStringNotContainsString('rel=', $result);
        $this->assertStringNotContainsString('target=', $result);
    }

    // -------------------------------------------------------------------------
    // formatBody — fallback (no format hook matches)
    // -------------------------------------------------------------------------

    public function testFormatBodyFallsBackToHtmlEscapeForUnknownFormat(): void
    {
        // Create extension WITHOUT registering the markdown handler to have a
        // plain-text fallback test. We override by using format with no handler.
        HookDispatcher::reset();
        $ext = $this->makeExt(); // registers markdown handler, but not for plain

        // Use null meta → format defaults to 'bbcode' which the default handler ignores
        $result = $ext->formatBody("Hello <b>World</b>\nLine2", null);
        $this->assertStringContainsString('&lt;b&gt;', $result);
        $this->assertStringContainsString('<br', $result);
    }

    // -------------------------------------------------------------------------
    // formatBody — with markdown
    // -------------------------------------------------------------------------

    public function testFormatBodyRendersMarkdownWhenMetaIsMarkdown(): void
    {
        $ext  = $this->makeExt();
        $meta = json_encode(['format' => 'markdown']);
        $result = $ext->formatBody('**bold text**', $meta);
        $this->assertStringContainsString('<strong>bold text</strong>', $result);
    }

    // -------------------------------------------------------------------------
    // getFilters / getFunctions — return arrays
    // -------------------------------------------------------------------------

    public function testGetFiltersReturnsArray(): void
    {
        $ext = $this->makeExt();
        $this->assertIsArray($ext->getFilters());
        $this->assertNotEmpty($ext->getFilters());
    }

    public function testGetFunctionsReturnsArray(): void
    {
        $ext = $this->makeExt();
        $this->assertIsArray($ext->getFunctions());
        $this->assertNotEmpty($ext->getFunctions());
    }

    // -------------------------------------------------------------------------
    // jsonLd
    // -------------------------------------------------------------------------

    public function testJsonLdRendersOneScriptTagPerNode(): void
    {
        $ext = $this->makeExt();

        $page1       = new WebPage();
        $page1->name = 'Page One';
        $page2       = new WebPage();
        $page2->name = 'Page Two';

        $result = $ext->jsonLd([$page1, $page2]);

        $this->assertSame(2, substr_count($result, '<script type="application/ld+json">'));
        $this->assertStringContainsString('Page One', $result);
        $this->assertStringContainsString('Page Two', $result);
    }

    public function testJsonLdSkipsNonJsonLdNodeEntries(): void
    {
        $ext    = $this->makeExt();
        $result = $ext->jsonLd(['not a node', 42]);
        $this->assertSame('', $result);
    }
}
