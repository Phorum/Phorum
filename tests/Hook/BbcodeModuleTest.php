<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use Phorum\Mod\Bbcode\BbcodeFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the full hook round-trip: module registers on a hook, dispatcher
 * fires the hook, and the module transforms data correctly.
 *
 * The BBCode module (mods/bbcode/bbcode.php) self-registers on require, so
 * we load it once for the class and isolate dispatcher state per test via
 * setUp/tearDown reset + manual re-registration.
 */
class BbcodeModuleTest extends TestCase
{
    private static bool $moduleLoaded = false;

    public static function setUpBeforeClass(): void
    {
        // Load the module file once — this defines BbcodeFormatter and runs the
        // initial self-registration. Subsequent tests re-register manually.
        if (!self::$moduleLoaded) {
            HookDispatcher::reset();
            require_once dirname(__DIR__, 2) . '/mods/bbcode/bbcode.php';
            self::$moduleLoaded = true;
        }
    }

    protected function setUp(): void
    {
        // Fresh dispatcher for every test; manually register the same callback
        // the module would register on a real boot.
        HookDispatcher::reset();
        HookDispatcher::getInstance()->register(
            hook:     'format',
            callback: static function (string $body, string $format): ?string {
                if ($format !== 'bbcode') {
                    return null;
                }
                return (new BbcodeFormatter())->render($body);
            },
            priority: 10,
        );
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    // -------------------------------------------------------------------------
    // Hook registration
    // -------------------------------------------------------------------------

    public function testFormatHookIsRegistered(): void
    {
        $this->assertTrue(HookDispatcher::getInstance()->hasHook('format'));
    }

    // -------------------------------------------------------------------------
    // Full round-trip through the dispatcher
    // -------------------------------------------------------------------------

    public function testBoldTagRoundTrip(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[b]hello[/b]', 'bbcode');
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testItalicTagRoundTrip(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[i]world[/i]', 'bbcode');
        $this->assertStringContainsString('<em>', $result);
    }

    public function testUrlTagRoundTrip(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[url=https://example.com]click[/url]', 'bbcode');
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('click', $result);
    }

    // -------------------------------------------------------------------------
    // Bare-URL / bare-email autolinking
    // -------------------------------------------------------------------------

    public function testBareUrlIsAutolinked(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', 'See https://example.com/page for info.', 'bbcode');
        $this->assertStringContainsString('<a href="https://example.com/page" rel="nofollow">https://example.com/page</a>', $result);
    }

    public function testBareWwwUrlIsAutolinkedWithHttpPrefix(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', 'Visit www.example.com today.', 'bbcode');
        $this->assertStringContainsString('<a href="http://www.example.com" rel="nofollow">www.example.com</a>', $result);
    }

    public function testBareEmailIsAutolinked(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', 'Contact someone@example.com for help.', 'bbcode');
        $this->assertStringContainsString('<a href="mailto:someone@example.com">someone@example.com</a>', $result);
    }

    public function testExplicitUrlTagIsNotDoubleAutolinked(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[url=https://example.com/explicit]click here[/url]', 'bbcode');
        $this->assertSame(1, substr_count($result, '<a '));
        $this->assertStringContainsString('>click here<', $result);
    }

    public function testBareUrlInsideCodeBlockIsNotAutolinked(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[code]https://example.com/should-not-link[/code]', 'bbcode');
        $this->assertStringNotContainsString('<a ', $result);
        $this->assertStringContainsString('https://example.com/should-not-link', $result);
    }

    public function testImgTagUrlIsNotAlsoAutolinked(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[img]https://example.com/pic.png[/img]', 'bbcode');
        $this->assertStringContainsString('<img src="https://example.com/pic.png" class="bbcode" alt=""/>', $result);
        $this->assertStringNotContainsString('<a ', $result);
    }

    // -------------------------------------------------------------------------
    // [url]/[img] scheme safety
    // -------------------------------------------------------------------------

    /**
     * A URL whose scheme is not on the allowlist must never reach an href.
     *
     * The tab/CR/LF cases are the ones that regressed: browsers strip those
     * characters out of a URL before parsing it, so "java<TAB>script:" runs as
     * "javascript:", while PHP's parse_url() returns null for them — which the
     * old `?? ''` check read as "no scheme, therefore a safe relative link".
     *
     * @param string $url The attacker-supplied [url= ] target.
     */
    #[DataProvider('unsafeUrlProvider')]
    public function testUnsafeUrlSchemeIsNotLinked(string $url): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[url=' . $url . ']click[/url]', 'bbcode');

        $this->assertStringNotContainsString('<a ', $result, 'unsafe scheme was linked');
        $this->assertStringContainsString('click', $result, 'label should still render as plain text');
    }

    /**
     * The same allowlist applies to [img], which drops the tag entirely
     * rather than falling back to a text label.
     *
     * @param string $url The attacker-supplied [img] target.
     */
    #[DataProvider('unsafeUrlProvider')]
    public function testUnsafeImgSchemeIsNotRendered(string $url): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[img]' . $url . '[/img]', 'bbcode');

        $this->assertStringNotContainsString('<img', $result);
    }

    /**
     * Script-bearing URL shapes that must all be rejected: the plain and
     * mixed-case forms, the control-character-split forms browsers rejoin,
     * a leading space, an embedded NUL, and other non-allowlisted schemes.
     *
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'plain javascript'     => ['javascript:alert(1)'],
            'mixed case'           => ['JaVaScRiPt:alert(1)'],
            'tab in scheme'        => ["java\tscript:alert(1)"],
            'newline in scheme'    => ["java\nscript:alert(1)"],
            'carriage return'      => ["java\rscript:alert(1)"],
            'leading space'        => [' javascript:alert(1)'],
            'embedded NUL'         => ["j\x00avascript:alert(1)"],
            'vbscript'             => ['vbscript:msgbox(1)'],
            'data uri'             => ['data:text/html,<h1>x</h1>'],
        ];
    }

    /**
     * The allowed schemes, plus relative and protocol-relative links, must
     * still render — the fix tightens the check without narrowing what a
     * normal post can link to.
     *
     * @param string $url      The [url= ] target.
     * @param string $expected The href value expected in the output.
     */
    #[DataProvider('safeUrlProvider')]
    public function testSafeUrlSchemeIsStillLinked(string $url, string $expected): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[url=' . $url . ']click[/url]', 'bbcode');

        $this->assertStringContainsString('href="' . $expected . '"', $result);
    }

    /**
     * Link shapes that must keep working, including a query string whose `&`
     * has to survive the decode/re-escape round trip in safeUrl().
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function safeUrlProvider(): array
    {
        return [
            'https'             => ['https://example.com/a', 'https://example.com/a'],
            'http'              => ['http://example.com/a', 'http://example.com/a'],
            'ftp'               => ['ftp://files.example.com/f.zip', 'ftp://files.example.com/f.zip'],
            'relative path'     => ['/forum/3/thread/7', '/forum/3/thread/7'],
            'protocol relative' => ['//example.com/x', '//example.com/x'],
            'query ampersand'   => ['https://example.com/a?x=1&y=2', 'https://example.com/a?x=1&amp;y=2'],
        ];
    }

    public function testCodeBlockRoundTrip(): void
    {
        $result = HookDispatcher::getInstance()->dispatch('format', '[code]echo "hi";[/code]', 'bbcode');
        $this->assertStringContainsString('<pre', $result);
        $this->assertStringContainsString('echo', $result);
    }

    public function testNonBbcodeFormatReturnsNull(): void
    {
        // Handler should return null for non-bbcode formats, leaving data unchanged
        $result = HookDispatcher::getInstance()->dispatch('format', '**bold**', 'markdown');
        // No handler claimed it — data passes through unmodified
        $this->assertSame('**bold**', $result);
    }

    // -------------------------------------------------------------------------
    // Multiple handlers — only the matching one acts
    // -------------------------------------------------------------------------

    public function testSecondHandlerCanHandleDifferentFormat(): void
    {
        // Register a second handler for a custom format
        HookDispatcher::getInstance()->register(
            hook:     'format',
            callback: static function (string $body, string $format): ?string {
                if ($format !== 'shout') {
                    return null;
                }
                return strtoupper($body);
            },
            priority: 10,
        );

        $bbcode = HookDispatcher::getInstance()->dispatch('format', '[b]hi[/b]', 'bbcode');
        $shout  = HookDispatcher::getInstance()->dispatch('format', 'hello', 'shout');
        $other  = HookDispatcher::getInstance()->dispatch('format', 'plain', 'plain');

        $this->assertStringContainsString('<strong>', $bbcode);
        $this->assertSame('HELLO', $shout);
        $this->assertSame('plain', $other); // no handler matched — unchanged
    }

    // -------------------------------------------------------------------------
    // BbcodeFormatter unit coverage (exercises the module class directly)
    // -------------------------------------------------------------------------

    public function testXssInBodyIsEscaped(): void
    {
        $formatter = new BbcodeFormatter();
        $result    = $formatter->render('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testNestedBoldItalic(): void
    {
        $formatter = new BbcodeFormatter();
        $result    = $formatter->render('[b][i]bold-italic[/i][/b]');
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
    }

    public function testQuoteTag(): void
    {
        $formatter = new BbcodeFormatter();
        $result    = $formatter->render('[quote]quoted text[/quote]');
        $this->assertStringContainsString('blockquote', $result);
        $this->assertStringContainsString('quoted text', $result);
    }

    public function testListTag(): void
    {
        $formatter = new BbcodeFormatter();
        $result    = $formatter->render("[list]\n[*]item one\n[*]item two\n[/list]");
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>', $result);
    }

    // -------------------------------------------------------------------------
    // [quote] nesting depth
    // -------------------------------------------------------------------------

    /** Render $body through the bbcode format hook. */
    private function render(string $body): string
    {
        return HookDispatcher::getInstance()->dispatch('format', $body, 'bbcode');
    }

    /** Build $depth nested [quote] tags around a marker. */
    private function nestedQuotes(int $depth): string
    {
        return str_repeat('[quote]', $depth) . 'inner' . str_repeat('[/quote]', $depth);
    }

    /** A normal quote chain resolves completely. */
    public function testOrdinaryNestingResolvesFully(): void
    {
        $result = $this->render($this->nestedQuotes(3));

        $this->assertSame(3, substr_count($result, '<blockquote'));
        $this->assertStringNotContainsString('[quote]', $result);
        $this->assertStringContainsString('inner', $result);
    }

    /** Nesting exactly at the limit still resolves completely. */
    public function testNestingAtTheLimitResolvesFully(): void
    {
        $result = $this->render($this->nestedQuotes(20));

        $this->assertSame(20, substr_count($result, '<blockquote'));
        $this->assertStringNotContainsString('[quote]', $result);
    }

    /**
     * Past the limit, resolution stops and the remaining tags stay as literal
     * text. Each pass of the loop resolves one level and rescans a string that
     * has grown, so without a cap the cost is quadratic in depth — a single
     * 64 KB post of nested quotes took ~185 ms of CPU on every view, with no
     * output cache and the feed path rendering bodies too.
     */
    public function testNestingBeyondTheLimitStopsAtTheCap(): void
    {
        $result = $this->render($this->nestedQuotes(60));

        $this->assertSame(20, substr_count($result, '<blockquote'), 'more levels resolved than the cap allows');
        $this->assertStringContainsString('[quote]', $result, 'unresolved tags should remain as literal text');
    }

    /**
     * The cap bounds work by nesting depth, not by size: any number of
     * non-nested quotes still resolves, because they all match in one pass.
     */
    public function testManySiblingQuotesAreUnaffectedByTheCap(): void
    {
        $result = $this->render(str_repeat('[quote]x[/quote]', 100));

        $this->assertSame(100, substr_count($result, '<blockquote'));
        $this->assertStringNotContainsString('[quote]', $result);
    }

    /** Leftover markup is inert — it was escaped before any tag processing. */
    public function testUnresolvedQuoteMarkupCannotCarryHtml(): void
    {
        $result = $this->render(str_repeat('[quote]', 60) . '<script>alert(1)</script>' . str_repeat('[/quote]', 60));

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }
}
