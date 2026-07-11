<?php
declare(strict_types=1);

namespace Phorum\Core;

class Config
{
    private array $data;

    public function __construct(string $configFile)
    {
        if (!file_exists($configFile)) {
            throw new \RuntimeException("Config file not found: {$configFile}");
        }
        $this->data = require $configFile;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
