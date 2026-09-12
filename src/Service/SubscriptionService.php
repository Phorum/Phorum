<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\Config;
use Phorum\Core\SiteSettings;
use Phorum\Core\Url;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;

class SubscriptionService
{
    public const SUB_NONE     = SubscriberMapper::SUB_NONE;
    public const SUB_MESSAGE  = SubscriberMapper::SUB_MESSAGE;
    public const SUB_DIGEST   = SubscriberMapper::SUB_DIGEST;
    public const SUB_BOOKMARK = SubscriberMapper::SUB_BOOKMARK;

    private readonly PermissionService $perms;

    public function __construct(
        private readonly SubscriberMapper $subscribers,
        private readonly UserMapper       $users,
        private readonly MailService      $mailer,
        private readonly Config           $config,
        ?PermissionService                $perms = null,
    ) {
        $this->perms = $perms ?? new PermissionService(new UserPermissionMapper());
    }

    public function subscribe(int $userId, int $forumId, int $thread, int $type): void
    {
        $this->subscribers->subscribe($userId, $forumId, $thread, $type);
    }

    public function unsubscribe(int $userId, int $forumId, int $thread): void
    {
        $this->subscribers->unsubscribe($userId, $forumId, $thread);
    }

    /**
     * Return the current sub_type for a user, or SUB_NONE if not subscribed.
     */
    public function getSubscription(int $userId, int $forumId, int $thread): int
    {
        return $this->subscribers->getSubscription($userId, $forumId, $thread)
            ?? self::SUB_NONE;
    }

    /**
     * Send email notifications to all SUB_MESSAGE subscribers for the given post.
     * Call after a message reaches approved status (on post or on moderation approval).
     *
     * @param int $excludeUserId  The author — never notified about their own post.
     */
    public function notifySubscribers(Message $message, Forum $forum, int $excludeUserId): void
    {
        $recipients = $this->readableBy(
            $this->subscribers->listEmailSubscribers($message->forum_id, $message->thread, $excludeUserId),
            $forum,
        );

        if (empty($recipients)) {
            return;
        }

        $siteName  = SiteSettings::name();
        $baseUrl   = rtrim((string) $this->config->get('base_url', ''), '/');
        $readUrl   = $baseUrl . Url::thread($message->forum_id, $message->thread, $message->message_id);

        foreach ($recipients as $row) {
            // matched_thread == 0 means this recipient is subscribed to the
            // whole forum, not just this thread — link to the matching
            // unsubscribe endpoint so the click actually removes the
            // subscription that caused this email.
            $followPath = ((int) $row['matched_thread']) === 0
                ? "/forum/{$message->forum_id}/follow"
                : "/follow/{$message->thread}";
            $unsubUrl    = $baseUrl . $followPath . '?action=remove';
            $bookmarkUrl = $baseUrl . $followPath . '?action=bookmark';
            $displayName = $row['display_name'] !== '' ? $row['display_name'] : $row['username'];

            $body = "Hello,\n\n"
                  . "You are receiving this email because you are following the topic:\n\n"
                  . "  {$message->subject}\n"
                  . "  <{$readUrl}>\n\n"
                  . "To stop following this topic click here:\n"
                  . "<{$unsubUrl}>\n\n"
                  . "To stop receiving emails but keep the bookmark:\n"
                  . "<{$bookmarkUrl}>\n";

            $this->mailer->send(
                toAddress: $row['email'],
                toName:    $displayName,
                subject:   "[{$siteName}] New reply: {$message->subject}",
                body:      $body,
            );
        }
    }

    /**
     * Drop subscribers who can no longer read the forum.
     *
     * A subscription row outlives the permission that allowed it — a user
     * removed from a group, or a forum made private after the fact, would
     * otherwise keep receiving the subject line of every new post in it. This
     * check belongs here as well as at subscribe time, because only this side
     * sees the permissions as they are when the mail actually goes out.
     *
     * @param  array<int, array<string, mixed>> $recipients Rows from listEmailSubscribers().
     * @return array<int, array<string, mixed>>
     */
    private function readableBy(array $recipients, Forum $forum): array
    {
        if (empty($recipients)) {
            return [];
        }

        $users = $this->users->findByIds(array_map(
            static fn(array $row): int => (int) $row['user_id'],
            $recipients
        ));

        return array_values(array_filter(
            $recipients,
            function (array $row) use ($users, $forum): bool {
                $user = $users[(int) $row['user_id']] ?? null;
                return $user !== null && $this->perms->canRead($forum, $user);
            }
        ));
    }

    /**
     * Notify forum moderators about a new post (respects forum->email_moderators
     * and per-user moderation_email flag).
     */
    public function notifyModerators(Message $message, Forum $forum): void
    {
        if (!$forum->email_moderators) {
            return;
        }

        $moderators = $this->users->findModeratorsForForum($forum->forum_id);
        if (empty($moderators)) {
            return;
        }

        $siteName = SiteSettings::name();
        $baseUrl  = rtrim((string) $this->config->get('base_url', ''), '/');

        $isModerated = $message->status !== MessageMapper::STATUS_APPROVED;
        $actionUrl   = $isModerated
            ? $baseUrl . "/moderate/message/{$message->message_id}/approve"
            : $baseUrl . Url::thread($message->forum_id, $message->thread, $message->message_id);

        foreach ($moderators as $row) {
            $displayName = $row['display_name'] !== '' ? $row['display_name'] : $row['username'];

            if ($isModerated) {
                $body = "There has been a new message sent to a forum which you are moderating.\n\n"
                      . "The message has the subject: {$message->subject}\n\n"
                      . "It can be reviewed and approved here:\n<{$actionUrl}>\n";
                $subject = "[{$siteName}] New message pending approval: {$message->subject}";
            } else {
                $body = "There has been a new message sent to a forum which you are moderating.\n\n"
                      . "The message was posted by {$message->author} "
                      . "with the subject: {$message->subject}\n\n"
                      . "It can be read here:\n<{$actionUrl}>\n";
                $subject = "[{$siteName}] New message: {$message->subject}";
            }

            $this->mailer->send(
                toAddress: $row['email'],
                toName:    $displayName,
                subject:   $subject,
                body:      $body,
            );
        }
    }
}
