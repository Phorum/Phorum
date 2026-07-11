<?php
declare(strict_types=1);

namespace Phorum\Http;

/**
 * Immutable HTTP request value object passed to every controller action.
 * Wraps superglobals so controllers can be tested without mutating globals.
 */
class Request
{
    /**
     * @param array $query   $_GET
     * @param array $post    $_POST
     * @param array $server  $_SERVER
     * @param array $tokens  Router match tokens (path parameters)
     * @param array $files   $_FILES
     */
    public function __construct(
        public readonly array $query   = [],
        public readonly array $post    = [],
        public readonly array $server  = [],
        public readonly array $tokens  = [],
        public readonly array $files   = [],
    ) {}

    public function isPost(): bool
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
