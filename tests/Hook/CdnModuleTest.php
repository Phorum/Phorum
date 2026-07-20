<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use Phorum\Mod\Cdn\CdnHooks;
use Phorum\Mod\Cdn\CdnService;
use PHPUnit\Framework\TestCase;

/**
 * Tests the full hook round-trip: the cdn module registers on the core
 * attachment_url/avatar_url hooks, the dispatcher fires them, and CdnHooks
 * delegates to CdnService correctly — using the real CdnHooks::register()
 * wiring, following the pattern established by S3StorageModuleTest.
 */
class CdnModuleTest extends TestCase
{
    private static bool $moduleLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$moduleLoaded) {
            $base = dirname(__DIR__, 2) . '/mods/cdn';
            require_once $base . '/CdnService.php';
            require_once $base . '/CdnHooks.php';
            self::$moduleLoaded = true;
        }
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function registerWith(CdnService $cdn): HookDispatcher
    {
        HookDispatcher::reset();
        $hooks = HookDispatcher::getInstance();
        CdnHooks::register($cdn, $hooks);
        return $hooks;
    }

    public function testBothHooksAreRegistered(): void
    {
        $hooks = $this->registerWith($this->createMock(CdnService::class));

        foreach (['attachment_url', 'avatar_url'] as $hookName) {
            $this->assertTrue($hooks->hasHook($hookName), "expected {$hookName} to be registered");
        }
    }

    public function testAttachmentUrlReturnsCdnUrlWhenConfigured(): void
    {
        $cdn = $this->createMock(CdnService::class);
        $cdn->method('urlFor')->with('/file/7/photo.jpg')->willReturn('https://cdn.example.test/file/7/photo.jpg');

        $hooks  = $this->registerWith($cdn);
        $result = $hooks->dispatch('attachment_url', '/file/7/photo.jpg', '/file/7/photo.jpg', 7, 'photo.jpg');

        $this->assertSame('https://cdn.example.test/file/7/photo.jpg', $result);
    }

    public function testAttachmentUrlFallsThroughToDefaultWhenNotConfigured(): void
    {
        $cdn = $this->createMock(CdnService::class);
        $cdn->method('urlFor')->willReturn(null);

        $hooks  = $this->registerWith($cdn);
        $result = $hooks->dispatch('attachment_url', '/file/7/photo.jpg', '/file/7/photo.jpg', 7, 'photo.jpg');

        $this->assertSame('/file/7/photo.jpg', $result);
    }

    public function testAvatarUrlReturnsCdnUrlWhenConfigured(): void
    {
        $cdn = $this->createMock(CdnService::class);
        $cdn->method('urlFor')->with('/avatar/42')->willReturn('https://cdn.example.test/avatar/42');

        $hooks  = $this->registerWith($cdn);
        $result = $hooks->dispatch('avatar_url', '/avatar/42', '/avatar/42', 42);

        $this->assertSame('https://cdn.example.test/avatar/42', $result);
    }

    public function testAvatarUrlFallsThroughToDefaultWhenNotConfigured(): void
    {
        $cdn = $this->createMock(CdnService::class);
        $cdn->method('urlFor')->willReturn(null);

        $hooks  = $this->registerWith($cdn);
        $result = $hooks->dispatch('avatar_url', '/avatar/42', '/avatar/42', 42);

        $this->assertSame('/avatar/42', $result);
    }
}
