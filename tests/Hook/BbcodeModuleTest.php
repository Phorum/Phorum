<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use Phorum\Mod\Bbcode\BbcodeFormatter;
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
}
