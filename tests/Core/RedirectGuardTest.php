<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\RedirectGuard;
use PHPUnit\Framework\TestCase;

class RedirectGuardTest extends TestCase
{
    public function testSanitizePathAllowsRelativePath(): void
    {
        $this->assertSame('/forum/5/thread/10', RedirectGuard::sanitizePath('/forum/5/thread/10'));
    }

    public function testSanitizePathRejectsProtocolRelativeUrl(): void
    {
        $this->assertSame('/', RedirectGuard::sanitizePath('//evil.com/phish'));
    }

    public function testSanitizePathRejectsExternalUrl(): void
    {
        $this->assertSame('/', RedirectGuard::sanitizePath('https://evil.com'));
    }

    public function testSanitizePathRejectsEmptyOrNull(): void
    {
        $this->assertSame('/', RedirectGuard::sanitizePath(''));
        $this->assertSame('/', RedirectGuard::sanitizePath(null));
    }

    public function testChangePasswordUrlEncodesRedirectTarget(): void
    {
        $this->assertSame(
            '/user/change-password?redirect=%2Fforum%2F5%2Fthread%2F10',
            RedirectGuard::changePasswordUrl('/forum/5/thread/10')
        );
    }

    public function testChangePasswordUrlSanitizesUnsafeRedirectTarget(): void
    {
        $this->assertSame(
            '/user/change-password?redirect=%2F',
            RedirectGuard::changePasswordUrl('//evil.com')
        );
    }
}
