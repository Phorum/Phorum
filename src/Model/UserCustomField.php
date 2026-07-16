<?php
declare(strict_types=1);

namespace Phorum\Model;

class UserCustomField
{
    public int    $user_id = 0;
    public int    $type    = 0; // FK → the field's id in the PROFILE_FIELDS setting
    public string $data    = '';
}
