<?php
declare(strict_types=1);

namespace Phorum\Model;

class MessageTracking
{
    public int    $track_id    = 0;
    public int    $message_id  = 0;
    public int    $user_id     = 0;
    public int    $time        = 0;
    /** Full body text before this edit. */
    public string $diff_body    = '';
    /** Full subject text before this edit. */
    public string $diff_subject = '';
}
