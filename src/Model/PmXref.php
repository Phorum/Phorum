<?php
declare(strict_types=1);

namespace Phorum\Model;

class PmXref
{
    public int    $pm_xref_id     = 0;
    public int    $user_id        = 0;
    public int    $pm_message_id  = 0;
    public int    $pm_folder_id   = 0;    // 0 = special folder
    public string $special_folder = '';   // 'inbox' | 'outbox' | '' for custom
    public int    $read_flag      = 0;
    public int    $reply_flag     = 0;
}
