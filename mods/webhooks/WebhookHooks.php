<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\MessageMapper;
use Phorum\Model\Ban;
use Phorum\Model\Message;
use Phorum\Model\PmMessage;
use Phorum\Model\User;

/**
 * Registers the hook callbacks that turn core events into webhook
 * deliveries. Kept separate from webhooks.php (the module's boot file) so
 * tests can call register() directly with an injected WebhookDispatcher and
 * HookDispatcher, exercising the real wiring rather than a hand-copied
 * duplicate of it.
 *
 * Payloads sent to the dispatcher are deliberately curated, narrower
 * subsets of what the source hooks pass — never the raw User model (which
 * carries password hashes and session tokens) or PM message bodies (private
 * content), even though those are technically available here.
 */
class WebhookHooks
{
    public static function register(
        WebhookDispatcher $dispatcher,
        ?HookDispatcher   $hooks    = null,
        ?MessageMapper    $messages = null,
    ): void {
        $hooks    ??= HookDispatcher::getInstance();
        $messages ??= new MessageMapper();

        $hooks->register('after_post', static function (Message $msg) use ($dispatcher): ?Message {
            $dispatcher->dispatch('message.created', self::messageData($msg));
            return null;
        });

        $hooks->register('after_approve', static function (Message $msg) use ($dispatcher): ?Message {
            $dispatcher->dispatch('message.approved', self::messageData($msg));
            return null;
        });

        $hooks->register('delete', static function (array $messageIds) use ($dispatcher, $messages): ?array {
            foreach ($messageIds as $id) {
                $msg = $messages->load((int) $id);
                if ($msg !== null) {
                    $dispatcher->dispatch('message.deleted', self::messageData($msg));
                }
            }
            return null;
        });

        $hooks->register('after_register', static function (User $user) use ($dispatcher): ?User {
            $dispatcher->dispatch('user.registered', [
                'user_id'      => $user->user_id,
                'username'     => $user->username,
                'display_name' => $user->display_name,
                'email'        => $user->email,
                'date_added'   => $user->date_added,
            ]);
            return null;
        });

        $hooks->register('after_ban_create', static function (Ban $ban) use ($dispatcher): ?Ban {
            $dispatcher->dispatch('user.banned', [
                'id'       => $ban->id,
                'forum_id' => $ban->forum_id,
                'type'     => $ban->type,
                'string'   => $ban->string,
            ]);
            return null;
        });

        $hooks->register('after_shadow_ban_change', static function (array $payload) use ($dispatcher): ?array {
            /** @var User $user */
            $user = $payload['user'];
            $dispatcher->dispatch('user.shadow_ban_changed', [
                'user_id'  => $user->user_id,
                'username' => $user->username,
                'enabled'  => (bool) $payload['enabled'],
            ]);
            return null;
        });

        $hooks->register('pm_sent', static function (PmMessage $pm) use ($dispatcher): ?PmMessage {
            // Deliberately excludes $pm->message (the PM body) — private
            // content stays private even when this opt-in event is subscribed to.
            $dispatcher->dispatch('pm.sent', [
                'pm_message_id' => $pm->pm_message_id,
                'user_id'       => $pm->user_id,
                'author'        => $pm->author,
                'subject'       => $pm->subject,
                'datestamp'     => $pm->datestamp,
            ]);
            return null;
        });
    }

    /** @return array{message_id:int,forum_id:int,thread:int,parent_id:int,user_id:int,author:string,subject:string,body:string,status:int,datestamp:int} */
    private static function messageData(Message $msg): array
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
