<?php
declare(strict_types=1);

namespace Phorum\Model;

class CustomField
{
    public int    $relation_id = 0;
    public int    $field_type  = 0;
    public int    $type        = 0; // FK → custom_fields_config.id
    public string $data        = '';
}
