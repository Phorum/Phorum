<?php
declare(strict_types=1);

namespace Phorum\Hook;

class HookDispatcher
{
    private static ?self $instance = null;

    /** @var array<string, list<array{callback: callable, priority: int}>> */
    private array $hooks = [];

    private bool $lastDispatchWasClaimed = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][] = ['callback' => $callback, 'priority' => $priority];
        usort($this->hooks[$hook], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Call all callbacks registered for $hook. Each callback receives the
     * current value of $data (plus any extra $args) and its return value
     * becomes the new $data for the next callback in the chain.
     */
    public function dispatch(string $hook, mixed $data = null, mixed ...$args): mixed
    {
        $this->lastDispatchWasClaimed = false;

        if (empty($this->hooks[$hook])) {
            return $data;
        }

        foreach ($this->hooks[$hook] as $entry) {
            $result = ($entry['callback'])($data, ...$args);
            if ($result !== null) {
                $data                         = $result;
                $this->lastDispatchWasClaimed = true;
            }
        }

        return $data;
    }

    /** True if the most recent dispatch() had at least one handler return non-null. */
    public function lastDispatchWasClaimed(): bool
    {
        return $this->lastDispatchWasClaimed;
    }

    public function hasHook(string $hook): bool
    {
        return !empty($this->hooks[$hook]);
    }

    /**
     * Reset the singleton and clear all registered hooks.
     * Intended for use in tests only.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
