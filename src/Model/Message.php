<?php
declare(strict_types=1);

namespace Phorum\Model;

class Message
{
    public int    $message_id      = 0;
    public int    $forum_id        = 0;
    public int    $thread          = 0;
    public int    $parent_id       = 0;
    public int    $user_id         = 0;
    public string $author          = '';
    public string $subject         = '';
    public string $body            = '';
    public string $email           = '';
    public string $ip              = '';
    public int    $status          = 2;   // 2=approved, 0=unapproved, -1=deleted
    public string $msgid           = '';
    public int    $modifystamp     = 0;
    public int    $thread_count    = 0;
    public int    $moderator_post  = 0;
    public int    $sort            = 2;   // 2=normal, 1=sticky
    public int    $datestamp       = 0;
    public ?string $meta           = null; // JSON blob
    public int    $viewcount       = 0;
    public int    $threadviewcount = 0;
    public int    $closed          = 0;
    public int    $recent_message_id = 0;
    public int    $recent_user_id  = 0;
    public string $recent_author   = '';
    public int    $moved           = 0;
    public int    $hide_period     = 0;

    /** Populated at runtime for threaded display — not persisted. */
    public array  $children        = [];

    /** Populated at runtime from custom_fields — not persisted. */
    public array  $custom_fields   = [];

    /** Populated at runtime from the files table — not persisted. */
    public array  $attachments     = [];
}
