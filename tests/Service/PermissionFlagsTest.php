<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Service\PermissionFlags;
use PHPUnit\Framework\TestCase;

class PermissionFlagsTest extends TestCase
{
    public function testCombineOrsValidBits(): void
    {
        $this->assertSame(3, PermissionFlags::combine(['1', '2']));
    }

    public function testCombineIgnoresUnknownBits(): void
    {
        $this->assertSame(1, PermissionFlags::combine(['1', '99999']));
    }

    public function testCombineReturnsZeroForEmptyInput(): void
    {
        $this->assertSame(0, PermissionFlags::combine([]));
    }

    public function testDecodeReturnsPresentBits(): void
    {
        $this->assertSame([1, 64], PermissionFlags::decode(65));
    }

    public function testDecodeReturnsEmptyForZero(): void
    {
        $this->assertSame([], PermissionFlags::decode(0));
    }

    public function testCombineAndDecodeRoundTrip(): void
    {
        $bits  = [2, 8, 128];
        $combined = PermissionFlags::combine($bits);
        $this->assertSame($bits, PermissionFlags::decode($combined));
    }

    public function testFlagsIncludesViewAttachments(): void
    {
        $this->assertSame('View attachments', PermissionFlags::FLAGS[16]);
    }
}
