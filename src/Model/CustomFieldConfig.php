<?php
declare(strict_types=1);

namespace Phorum\Model;

class CustomFieldConfig
{
    public const FIELD_TYPE_USER    = 1;
    public const FIELD_TYPE_FORUM   = 2;
    public const FIELD_TYPE_MESSAGE = 3;

    public int    $id            = 0;
    public int    $field_type    = self::FIELD_TYPE_USER;
    public string $name          = '';
    public int    $length        = 255;
    public int    $html_disabled = 1;
    public int    $show_in_admin = 0;
    public int    $deleted       = 0;

    /**
     * Return the field name formatted as a human-readable label:
     * underscores replaced with spaces, title-cased.
     */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->name));
    }
}
