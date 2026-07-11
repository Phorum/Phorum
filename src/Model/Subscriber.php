<?php
declare(strict_types=1);

namespace Phorum\Model;

class Subscriber
{
    public int $user_id  = 0;
    public int $forum_id = 0;
    public int $thread   = 0;  // 0 = forum-level subscription
    public int $sub_type = 0;
}
