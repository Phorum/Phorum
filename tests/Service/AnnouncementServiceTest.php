<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Model\Message;
use Phorum\Service\AnnouncementService;
use Phorum\Service\NewflagService;
use PHPUnit\Framework\TestCase;

class AnnouncementServiceTest extends TestCase
{
    private function makeThread(int $id, int $datestamp, int $modifystamp): Message
    {
        $msg              = new Message();
        $msg->message_id  = $id;
        $msg->forum_id    = 7;
        $msg->subject     = "Thread {$id}";
        $msg->datestamp   = $datestamp;
        $msg->modifystamp = $modifystamp;
        return $msg;
    }

    public function testReturnsNullWhenNoForumConfigured(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(['forum_id' => 0, 'pages' => ['index' => true]]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->never())->method('findThreadsInForum');

        $service = new AnnouncementService($settings, $messages, $this->createMock(NewflagService::class));
        $this->assertNull($service->getAnnouncementsFor('index', 1));
    }

    public function testReturnsNullWhenPageNotEnabled(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([
            'forum_id' => 7,
            'pages'    => ['index' => true, 'list' => false, 'read' => false],
        ]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->never())->method('findThreadsInForum');

        $service = new AnnouncementService($settings, $messages, $this->createMock(NewflagService::class));
        $this->assertNull($service->getAnnouncementsFor('list', 1));
    }

    public function testReturnsThreadsForEnabledPage(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([
            'forum_id'       => 7,
            'number_to_show' => 5,
            'pages'          => ['index' => true],
        ]);

        $threads  = [$this->makeThread(1, time(), time())];
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->with(7, 5, 0)->willReturn($threads);

        $service = new AnnouncementService($settings, $messages, $this->createMock(NewflagService::class));
        $result  = $service->getAnnouncementsFor('index', 0);

        $this->assertSame(['threads' => $threads, 'new_counts' => []], $result);
    }

    public function testFiltersOutThreadsOlderThanDaysToShow(): void
    {
        $now = time();

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([
            'forum_id'     => 7,
            'days_to_show' => 1,
            'pages'        => ['index' => true],
        ]);

        $fresh    = $this->makeThread(1, $now, $now);
        $stale    = $this->makeThread(2, $now - (2 * 86400), $now - (2 * 86400));
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->willReturn([$fresh, $stale]);

        $service = new AnnouncementService($settings, $messages, $this->createMock(NewflagService::class));
        $result  = $service->getAnnouncementsFor('index', 0);

        $this->assertSame([$fresh], $result['threads']);
    }

    public function testOnlyShowUnreadFiltersToThreadsWithNewCounts(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([
            'forum_id'         => 7,
            'only_show_unread' => true,
            'pages'            => ['index' => true],
        ]);

        $read     = $this->makeThread(1, time(), time());
        $unread   = $this->makeThread(2, time(), time());
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->willReturn([$read, $unread]);

        $newflags = $this->createMock(NewflagService::class);
        $newflags->method('getNewCountsForThreads')->with(9, 7)->willReturn([2 => 1]);

        $service = new AnnouncementService($settings, $messages, $newflags);
        $result  = $service->getAnnouncementsFor('index', 9);

        $this->assertSame([$unread], $result['threads']);
        $this->assertSame([2 => 1], $result['new_counts']);
    }

    public function testReturnsNullWhenUnreadFilterLeavesNothing(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([
            'forum_id'         => 7,
            'only_show_unread' => true,
            'pages'            => ['index' => true],
        ]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->willReturn([$this->makeThread(1, time(), time())]);

        $newflags = $this->createMock(NewflagService::class);
        $newflags->method('getNewCountsForThreads')->willReturn([]);

        $service = new AnnouncementService($settings, $messages, $newflags);
        $this->assertNull($service->getAnnouncementsFor('index', 9));
    }
}
