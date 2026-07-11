<?php
declare(strict_types=1);

namespace Phorum\Model;

class Ban
{
    public int    $id       = 0;
    public int    $forum_id = 0;
    public int    $type     = 0;
    public int    $pcre     = 0;
    public string $string   = '';
    public string $comments = '';
}
