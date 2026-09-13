<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Config;
use Phorum\Core\SiteSettings;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\MailService;
use Phorum\Model\User;
use Phorum\Service\PermissionService;
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
        SiteSettings::clear();
    }

    /**
     * Build a SubscriptionService with mocked collaborators.
     *
     * $perms defaults to granting read access to everyone, so existing cases
     * exercise the notification path rather than the permission filter; the
     * filter has its own tests below.
     */
    private function makeService(
        ?SubscriberMapper  $subscribers = null,
        ?UserMapper        $users       = null,
        ?MailService       $mailer      = null,
        ?Config            $config      = null,
        ?PermissionService $perms       = null,
    ): SubscriptionService {
        $config ??= $this->createConfigMock(['base_url' => 'http://example.com']);

        if ($perms === null) {
            $perms = $this->createMock(PermissionService::class);
            $perms->method('canRead')->willReturn(true);
        }

        if ($users === null) {
            // notifySubscribers() now resolves each recipient to a User so it
            // can re-check read permission at send time; hand back one per id.
            $users = $this->createMock(UserMapper::class);
            $users->method('findByIds')->willReturnCallback(
                static function (array $ids): array {
                    $out = [];
                    foreach ($ids as $id) {
                        $u          = new User();
                        $u->user_id = (int) $id;
                        $u->active  = 1;
                        $out[(int) $id] = $u;
                    }
                    return $out;
                }
            );
        }

        return new SubscriptionService(
            $subscribers ?? $this->createMock(SubscriberMapper::class),
            $users,
            $mailer      ?? $this->createMock(MailService::class),
            $config,
            $perms,
        );
    }

    /** A minimal approved Message for the notification tests. */
    private function makeMessage(): Message
    {
        $msg             = new Message();
        $msg->forum_id   = 10;
        $msg->thread     = 100;
        $msg->message_id = 200;
        $msg->subject    = 'Test topic';
        return $msg;
    }

    /** A minimal Forum for the notification tests. */
    private function makeForum(): Forum
    {
        $forum           = new Forum();
        $forum->forum_id = 10;
        return $forum;
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
            ['user_id' => 2, 'email' => 'a@test.com', 'display_name' => 'Alice', 'username' => 'alice', 'matched_thread' => 100],
            ['user_id' => 3, 'email' => 'b@test.com', 'display_name' => '',      'username' => 'bob',   'matched_thread' => 100],
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

    public function testNotifySubscribersSubjectIncludesSiteName(): void
    {
        $settings = $this->createMock(\Phorum\Mapper\SettingMapper::class);
        $settings->method('getSetting')->willReturnMap([['site_name', 'My Test Forum']]);
        SiteSettings::initialize($settings, 'Phorum');

        $recipients = [
            ['user_id' => 2, 'email' => 'a@test.com', 'display_name' => 'Alice', 'username' => 'alice', 'matched_thread' => 100],
        ];
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn($recipients);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with($this->anything(), $this->anything(), $this->stringContains('[My Test Forum]'), $this->anything());

        $msg = new Message();
        $msg->forum_id = 10; $msg->thread = 100; $msg->message_id = 200; $msg->subject = 'Test topic';

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers($msg, new Forum(), 1);
    }

    public function testNotifySubscribersLinksToForumFollowForForumWideSubscriber(): void
    {
        $recipients = [
            ['user_id' => 2, 'email' => 'a@test.com', 'display_name' => 'Alice', 'username' => 'alice', 'matched_thread' => 0],
        ];
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn($recipients);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with(
                $this->anything(), $this->anything(), $this->anything(),
                $this->logicalAnd(
                    $this->stringContains('/forum/10/follow?action=remove'),
                    $this->stringContains('/forum/10/follow?action=bookmark'),
                    $this->logicalNot($this->stringContains('/follow/100?action')),
                ),
            );

        $msg = new Message();
        $msg->forum_id = 10; $msg->thread = 100; $msg->message_id = 200; $msg->subject = 'Test topic';

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers($msg, new Forum(), 1);
    }

    public function testNotifySubscribersLinksToThreadFollowForThreadSpecificSubscriber(): void
    {
        $recipients = [
            ['user_id' => 2, 'email' => 'a@test.com', 'display_name' => 'Alice', 'username' => 'alice', 'matched_thread' => 100],
        ];
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn($recipients);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())->method('send')
            ->with(
                $this->anything(), $this->anything(), $this->anything(),
                $this->logicalAnd(
                    $this->stringContains('/follow/100?action=remove'),
                    $this->stringContains('/follow/100?action=bookmark'),
                    $this->logicalNot($this->stringContains('/forum/10/follow')),
                ),
            );

        $msg = new Message();
        $msg->forum_id = 10; $msg->thread = 100; $msg->message_id = 200; $msg->subject = 'Test topic';

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer);
        $svc->notifySubscribers($msg, new Forum(), 1);
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
            ['user_id' => 2, 'email' => 'b@test.com', 'display_name' => '', 'username' => 'bob', 'matched_thread' => 1],
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

    // -------------------------------------------------------------------------
    // notifySubscribers — read permission at send time
    // -------------------------------------------------------------------------

    /**
     * A subscription row outlives the permission that created it. If a user is
     * removed from a group, or the forum is made private afterwards, they must
     * stop receiving the subject line of every new post in it.
     */
    public function testNotifySubscribersSkipsRecipientsWhoCanNoLongerRead(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn([
            ['user_id' => 7, 'email' => 'a@example.com', 'display_name' => 'A', 'username' => 'a', 'matched_thread' => 0],
        ]);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('send');

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(false);

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer, perms: $perms);
        $svc->notifySubscribers($this->makeMessage(), $this->makeForum(), excludeUserId: 0);
    }

    /** Only the recipients who still have read access are mailed. */
    public function testNotifySubscribersMailsOnlyPermittedRecipients(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn([
            ['user_id' => 7, 'email' => 'keep@example.com', 'display_name' => 'K', 'username' => 'k', 'matched_thread' => 0],
            ['user_id' => 8, 'email' => 'drop@example.com', 'display_name' => 'D', 'username' => 'd', 'matched_thread' => 0],
        ]);

        $sentTo = [];
        $mailer = $this->createMock(MailService::class);
        $mailer->method('send')->willReturnCallback(
            function (string $toAddress, string $toName, string $subject, string $body) use (&$sentTo): bool {
                $sentTo[] = $toAddress;
                return true;
            }
        );

        // Only user 7 can still read the forum.
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturnCallback(
            static fn($forum, $user): bool => $user !== null && $user->user_id === 7
        );

        $svc = $this->makeService(subscribers: $mapper, mailer: $mailer, perms: $perms);
        $svc->notifySubscribers($this->makeMessage(), $this->makeForum(), excludeUserId: 0);

        $this->assertSame(['keep@example.com'], $sentTo);
    }

    /** A subscriber whose account no longer exists is skipped, not fatal. */
    public function testNotifySubscribersSkipsMissingUsers(): void
    {
        $mapper = $this->createMock(SubscriberMapper::class);
        $mapper->method('listEmailSubscribers')->willReturn([
            ['user_id' => 99, 'email' => 'gone@example.com', 'display_name' => 'G', 'username' => 'g', 'matched_thread' => 0],
        ]);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByIds')->willReturn([]);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('send');

        $svc = $this->makeService(subscribers: $mapper, users: $users, mailer: $mailer);
        $svc->notifySubscribers($this->makeMessage(), $this->makeForum(), excludeUserId: 0);
    }
}
