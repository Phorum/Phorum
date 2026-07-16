<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testForum(): void
    {
        $this->assertSame('/forum/5', Url::forum(5));
    }

    public function testThreadWithoutMessageId(): void
    {
        $this->assertSame('/forum/5/thread/10', Url::thread(5, 10));
    }

    public function testThreadWithMessageId(): void
    {
        $this->assertSame('/forum/5/thread/10#msg-42', Url::thread(5, 10, 42));
    }
}
