<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Model\Message;
use Phorum\Service\FloodControlService;
use PHPUnit\Framework\TestCase;

class FloodControlServiceTest extends TestCase
{
    public function testReturnsZeroForGuest(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->never())->method('findLastByUser');
        $settings = $this->createMock(SettingMapper::class);

        $svc = new FloodControlService($messages, $settings);
        $this->assertSame(0, $svc->secondsRemaining(0));
    }

    public function testReturnsZeroWhenIntervalDisabled(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(0);

        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->never())->method('findLastByUser');

        $svc = new FloodControlService($messages, $settings);
        $this->assertSame(0, $svc->secondsRemaining(5));
    }

    public function testReturnsZeroWhenNoPriorPost(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(30);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findLastByUser')->willReturn(null);

        $svc = new FloodControlService($messages, $settings);
        $this->assertSame(0, $svc->secondsRemaining(5));
    }

    public function testReturnsRemainingSecondsWhenWithinInterval(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(30);

        $last            = new Message();
        $last->datestamp = time() - 10;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findLastByUser')->willReturn($last);

        $svc      = new FloodControlService($messages, $settings);
        $remaining = $svc->secondsRemaining(5);
        // Allow a 1-second tolerance for test execution time.
        $this->assertGreaterThanOrEqual(19, $remaining);
        $this->assertLessThanOrEqual(20, $remaining);
    }

    public function testReturnsZeroWhenIntervalHasElapsed(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(30);

        $last            = new Message();
        $last->datestamp = time() - 60;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findLastByUser')->willReturn($last);

        $svc = new FloodControlService($messages, $settings);
        $this->assertSame(0, $svc->secondsRemaining(5));
    }
}
