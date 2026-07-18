<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\MessageMapper;
use Phorum\Mod\Webhooks\WebhookDispatcher;
use Phorum\Mod\Webhooks\WebhookHooks;
use Phorum\Model\Ban;
use Phorum\Model\Message;
use Phorum\Model\PmMessage;
use Phorum\Model\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests the full hook round-trip: the webhooks module registers on each
 * source hook, the dispatcher fires it, and WebhookHooks builds the
 * correctly curated event payload — using the real WebhookHooks::register()
 * wiring (not a hand-copied duplicate), following the pattern established
 * by BbcodeModuleTest.
 */
class WebhooksModuleTest extends TestCase
{
    private static bool $moduleLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$moduleLoaded) {
            $base = dirname(__DIR__, 2) . '/mods/webhooks';
            require_once $base . '/Webhook.php';
            require_once $base . '/WebhookMapper.php';
            require_once $base . '/WebhookDispatcher.php';
            require_once $base . '/WebhookHooks.php';
            self::$moduleLoaded = true;
        }
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function registerWith(WebhookDispatcher $dispatcher, ?MessageMapper $messages = null): HookDispatcher
    {
        HookDispatcher::reset();
        $hooks = HookDispatcher::getInstance();
        WebhookHooks::register($dispatcher, $hooks, $messages);
        return $hooks;
    }

    public function testAllEventsAreRegistered(): void
    {
        $hooks = $this->registerWith($this->createMock(WebhookDispatcher::class));

        foreach ([
            'after_post', 'after_approve', 'delete', 'after_register',
            'after_ban_create', 'after_shadow_ban_change', 'pm_sent',
        ] as $hookName) {
            $this->assertTrue($hooks->hasHook($hookName), "expected {$hookName} to be registered");
        }
    }

    public function testAfterPostDispatchesMessageCreated(): void
    {
        $msg = $this->makeMessage();

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('message.created', $this->expectedMessageData($msg));

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('after_post', $msg);
    }

    public function testAfterPostDoesNotAlterMessageForOtherHandlers(): void
    {
        $msg = $this->makeMessage();
        $hooks = $this->registerWith($this->createMock(WebhookDispatcher::class));

        $result = $hooks->dispatch('after_post', $msg);
        $this->assertSame($msg, $result);
    }

    public function testAfterApproveDispatchesMessageApproved(): void
    {
        $msg = $this->makeMessage();

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('message.approved', $this->expectedMessageData($msg));

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('after_approve', $msg);
    }

    public function testDeleteLoadsEachMessageAndDispatchesMessageDeleted(): void
    {
        $msg = $this->makeMessage();
        $msg->message_id = 42;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(42)->willReturn($msg);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('message.deleted', $this->expectedMessageData($msg));

        $hooks = $this->registerWith($dispatcher, $messages);
        $hooks->dispatch('delete', [42]);
    }

    public function testDeleteSkipsIdsThatNoLongerLoad(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn(null);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $hooks = $this->registerWith($dispatcher, $messages);
        $hooks->dispatch('delete', [999]);
    }

    public function testAfterRegisterDispatchesUserRegisteredWithoutSensitiveFields(): void
    {
        $user               = new User();
        $user->user_id      = 7;
        $user->username     = 'bob';
        $user->display_name = 'Bob';
        $user->email        = 'bob@example.com';
        $user->date_added   = 1000;
        $user->password     = 'super-secret-hash';
        $user->sessid_lt    = 'long-term-token';
        $user->sessid_st    = 'short-term-token';

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('user.registered', [
                'user_id'      => 7,
                'username'     => 'bob',
                'display_name' => 'Bob',
                'email'        => 'bob@example.com',
                'date_added'   => 1000,
            ]);

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('after_register', $user);
    }

    public function testAfterBanCreateDispatchesUserBanned(): void
    {
        $ban           = new Ban();
        $ban->id       = 3;
        $ban->forum_id = 1;
        $ban->type     = 2;
        $ban->string   = 'spammer';

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('user.banned', ['id' => 3, 'forum_id' => 1, 'type' => 2, 'string' => 'spammer']);

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('after_ban_create', $ban);
    }

    public function testAfterShadowBanChangeDispatchesUserShadowBanChanged(): void
    {
        $user           = new User();
        $user->user_id  = 9;
        $user->username = 'carol';

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('user.shadow_ban_changed', ['user_id' => 9, 'username' => 'carol', 'enabled' => true]);

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('after_shadow_ban_change', ['user' => $user, 'enabled' => true]);
    }

    public function testPmSentDispatchesPmSentWithoutMessageBody(): void
    {
        $pm                 = new PmMessage();
        $pm->pm_message_id  = 5;
        $pm->user_id        = 2;
        $pm->author         = 'dave';
        $pm->subject        = 'Hi';
        $pm->message        = 'secret contents';
        $pm->datestamp      = 2000;

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with('pm.sent', [
                'pm_message_id' => 5,
                'user_id'       => 2,
                'author'        => 'dave',
                'subject'       => 'Hi',
                'datestamp'     => 2000,
            ]);

        $hooks = $this->registerWith($dispatcher);
        $hooks->dispatch('pm_sent', $pm);
    }

    // -------------------------------------------------------------------------

    private function makeMessage(): Message
    {
        $msg              = new Message();
        $msg->message_id  = 1;
        $msg->forum_id    = 2;
        $msg->thread      = 1;
        $msg->parent_id   = 0;
        $msg->user_id     = 5;
        $msg->author      = 'alice';
        $msg->subject     = 'Hi';
        $msg->body        = 'Body';
        $msg->status      = 2;
        $msg->datestamp   = 1000;
        return $msg;
    }

    private function expectedMessageData(Message $msg): array
    {
        return [
            'message_id' => $msg->message_id,
            'forum_id'   => $msg->forum_id,
            'thread'     => $msg->thread,
            'parent_id'  => $msg->parent_id,
            'user_id'    => $msg->user_id,
            'author'     => $msg->author,
            'subject'    => $msg->subject,
            'body'       => $msg->body,
            'status'     => $msg->status,
            'datestamp'  => $msg->datestamp,
        ];
    }
}
