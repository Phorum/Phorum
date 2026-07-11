<?php
declare(strict_types=1);

namespace Phorum\Model;

class ForumGroupXref
{
    public int $forum_group_xref_id = 0;
    public int $forum_id            = 0;
    public int $group_id            = 0;
    public int $permission          = 0;
}
