<?php
declare(strict_types=1);

namespace Phorum\Model;

class Setting
{
    public string $name = '';
    public string $type = 'V'; // 'V' = raw value, 'S' = PHP serialized
    public string $data = '';

    public function getValue(): mixed
    {
        return $this->type === 'S' ? unserialize($this->data, ['allowed_classes' => false]) : $this->data;
    }
}
