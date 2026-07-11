<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\MailService;
use Phorum\Service\SubscriptionService;
use PHPUnit\Framework\TestCase;

class SubscriptionServiceTest extends TestCase
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

    private function makeService(
        ?SubscriberMapper $subscribers = null,
        ?UserMapper       $users       = null,
        ?MailService      $mailer      = null,
        ?Config           $config      = null,
    ): SubscriptionService {
        $config ??= $this->createConfigMock(['site_name' => 'TestForum', 'base_url' => 'http://example.com']);
        return new SubscriptionService(
            $subscribers ?? $this->createMock(SubscriberMapper::class),
            $users       ?? $this->createMock(UserMapper::class),
            $mailer      ?? $this->createMock(MailService::class),
            $config,
        );
    }

    private function createConfigMock(array $values): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(fn($key, $default = null) => $values[$key] ?? $default);
        return $config;
    }

    // -------------------------------------------------------------------------
    // subscribe / unsubscribe / getSubscription
    // -------------------------------------------------------------------------

    public function testSubscribeDelegatesToMapper(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->expects($this->once())->method('subscribe')
            ->with(1, 10, 0, SubscriberMapper::SUB_MESSAGE);

        $svc = $this->makeService(subscribers: $mapper);
        $svc->subscribe(1, 10, 0, SubscriptionService::SUB_MESSAGE);
    }

    public function testUnsubscribeDelegatesToMapper(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->expects($this->once())->method('unsubscribe')->with(1, 10, 5);

        $svc = $this->makeService(subscribers: $mapper);
        $svc->unsubscribe(1, 10, 5);
    }

    public function testGetSubscriptionReturnsMappedValue(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('getSubscription')->willReturn(SubscriberMapper::SUB_BOOKMARK);

        $svc = $this->makeService(subscribers: $mapper);
        $this->assertSame(SubscriptionService::SUB_BOOKMARK, $svc->getSubscription(1, 10, 0));
    }

    public function testGetSubscriptionReturnsSubNoneWhenNotSubscribed(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('getSubscription')->willReturn(null);

        $svc = $this->makeService(subscribers: $mapper);
        $this->assertSame(SubscriptionService::SUB_NONE, $svc->getSubscription(1, 10, 0));
    }

    // -------------------------------------------------------------------------
    // notifySubscribers
    // -------------------------------------------------------------------------

    public function testNotifySubscribersSendsEmailToEachRecipient(): void
    {
        $recipients = [
            ['user_id' => 2, 'email' => 'a@test.com', 'display_name' => 'Alice', 'username' => 'alice'],
            ['user_id' => 3, 'email' => 'b@test.com', 'display_name' => '',      'username' => 'bob'],
        ];
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn($recipients);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->exactly(2))->method('send');

        $msg = new Message();
        $msg->forum_id   = 10;
        $msg->thread     = 100;
        $msg->message_id = 200;
        $msg->subject    = 'Test topic';

        $forum = new Forum();

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers($msg, $forum, 1);
    }

    public function testNotifySubscribersDoesNothingWhenNoRecipients(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn([]);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('send');

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers(new Message(), new Forum(), 1);
    }

    public function testNotifySubscribersUsesFallbackUsernameWhenDisplayNameEmpty(): void
    {
        $recipients = [
            ['user_id' => 2, 'email' => 'b@test.com', 'display_name' => '', 'username' => 'bob'],
        ];
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn($recipients);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with($this->anything(), 'bob', $this->anything(), $this->anything());

        $msg = new Message();
        $msg->forum_id = 1; $msg->thread = 1; $msg->message_id = 1; $msg->subject = 'x';

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers($msg, new Forum(), 1);
    }

    // -------------------------------------------------------------------------
    // notifyModerators
    // -------------------------------------------------------------------------

    public function testNotifyModeratorsSkipsWhenEmailModeratorsIsOff(): void
    {
        $users  = $this->createMock(UserMapper::class);
        $users->expects($this->never())->method('findModeratorsForForum');

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('send');

        $forum = new Forum();
        $forum->email_moderators = 0;

        $svc = $this->makeService(users: $users, mailer: $mailer);
        $svc->notifyModerators(new Message(), $forum);
    }

    public function testNotifyModeratorsSkipsWhenNoModeratorsFound(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findModeratorsForForum')->willReturn([]);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('send');

        $forum = new Forum();
        $forum->email_moderators = 1;
        $forum->forum_id         = 1;

        $svc = $this->makeService(users: $users, mailer: $mailer);
        $svc->notifyModerators(new Message(), $forum);
    }

    public function testNotifyModeratorsEmailsApprovedPostSubject(): void
    {
        $mods = [['user_id' => 5, 'email' => 'mod@test.com', 'display_name' => 'Mod', 'username' => 'mod']];
        $users = $this->createMock(UserMapper::class);
        $users->method('findModeratorsForForum')->willReturn($mods);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with('mod@test.com', 'Mod', $this->stringContains('New message'), $this->anything());

        $forum = new Forum();
        $forum->email_moderators = 1;
        $forum->forum_id         = 1;

        $msg = new Message();
        $msg->status    = \Phorum\Mapper\MessageMapper::STATUS_APPROVED;
        $msg->subject   = 'Hello';
        $msg->author    = 'alice';
        $msg->forum_id  = 1;
        $msg->thread    = 10;
        $msg->message_id = 11;

        $svc = $this->makeService(users: $users, mailer: $mailer);
        $svc->notifyModerators($msg, $forum);
    }
}
