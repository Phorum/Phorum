<?php
declare(strict_types=1);

namespace Phorum\Model;

class SearchEntry
{
    public int    $message_id   = 0;
    public int    $forum_id     = 0;
    public string $search_text  = '';
}
