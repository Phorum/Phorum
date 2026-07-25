<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use PHPUnit\Framework\TestCase;
use Phorum\Core\Themes;

class ThemesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
    }

    public function testAvailableIncludesShippedThemes(): void
    {
        $themes = Themes::available();
        $this->assertArrayHasKey('emerald', $themes);
        $this->assertArrayHasKey('amethyst', $themes);
        $this->assertSame('Phorum Emerald', $themes['emerald']);
    }

    public function testAvailableWithoutDefaultHasNoBlankEntry(): void
    {
        $themes = Themes::available();
        $this->assertArrayNotHasKey('', $themes);
    }

    public function testAvailableWithDefaultAddsLeadingBlankEntry(): void
    {
        $themes = Themes::available(withDefault: true);
        $keys   = array_keys($themes);
        $this->assertSame('', $keys[0]);
    }
}
