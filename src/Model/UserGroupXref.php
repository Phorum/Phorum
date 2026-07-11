<?php
declare(strict_types=1);

namespace Phorum\Model;

class UserGroupXref
{
    public int $user_group_xref_id = 0;
    public int $user_id            = 0;
    public int $group_id           = 0;
    public int $status             = 0;
}
