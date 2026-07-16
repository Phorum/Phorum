<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Builds the site-relative forum/thread permalink paths — the one place
 * that knows the "/forum/{id}[/thread/{id}][#msg-{id}]" shape, so it isn't
 * hand-copied at every controller/service that links to a forum or thread.
 */
final class Url
{
    public static function forum(int $forumId): string
    {
        return "/forum/{$forumId}";
    }

    /** $messageId, if given, appends a "#msg-{id}" fragment to scroll to that post. */
    public static function thread(int $forumId, int $threadId, ?int $messageId = null): string
    {
        $url = self::forum($forumId) . "/thread/{$threadId}";
        if ($messageId !== null) {
            $url .= "#msg-{$messageId}";
        }
        return $url;
    }
}
