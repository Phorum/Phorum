<?php
declare(strict_types=1);

namespace Phorum\Model;

class Report
{
    public int    $report_id        = 0;
    public int    $message_id       = 0;
    public int    $forum_id         = 0;
    public int    $reporter_user_id = 0;
    public string $reason           = '';
    public int    $status           = 0;
    public int    $created          = 0;
    public int    $resolved_user_id = 0;
    public int    $resolved_time    = 0;
}
