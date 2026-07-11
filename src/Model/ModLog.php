<?php
declare(strict_types=1);

namespace Phorum\Model;

class ModLog
{
    public int    $mod_log_id  = 0;
    public int    $user_id     = 0;
    public int    $forum_id    = 0;
    public string $action      = '';
    public string $object_type = '';
    public int    $object_id   = 0;
    public string $details     = '';
    public int    $time        = 0;
}
