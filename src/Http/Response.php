<?php
declare(strict_types=1);

namespace Phorum\Http;

/**
 * Immutable HTTP response value object returned by every controller action.
 * App::dispatch() receives this and sends it to the client.
 */
class Response
{
    /**
     * @param string               $body    Response body (HTML, text, binary).
     * @param int                  $status  HTTP status code.
     * @param array<string,string> $headers Additional headers (e.g. Location, Content-Type).
     */
    public function __construct(
        public readonly string $body    = '',
        public readonly int    $status  = 200,
        public readonly array  $headers = [],
    ) {}
}
