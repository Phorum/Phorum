<?php
declare(strict_types=1);

namespace Phorum\Model;

class PmMessage
{
    public int     $pm_message_id = 0;
    public int     $user_id       = 0;   // sender
    public string  $author        = '';
    public string  $subject       = '';
    public string  $message       = '';
    public int     $datestamp     = 0;
    public ?string $meta          = null; // JSON: {"recipients":[{"user_id":N,"username":"..."},...]}
}
