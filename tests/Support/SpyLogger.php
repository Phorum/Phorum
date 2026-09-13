<?php
declare(strict_types=1);

namespace Phorum\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * A PSR-3 logger that records every write in memory instead of emitting it.
 *
 * Used wherever a class under test logs on a failure branch. Injecting this in
 * place of the default ErrorLogLogger keeps log lines out of the test run's
 * stderr (error_log() writes there under the CLI SAPI) and lets a test assert
 * that the failure was actually reported.
 */
class SpyLogger extends AbstractLogger
{
    /**
     * Every record written so far, in order.
     *
     * @var list<array{level:string,message:string,context:array<string,mixed>}>
     */
    public array $records = [];

    /**
     * Record one write rather than emitting it.
     *
     * @param  mixed               $level   One of the Psr\Log\LogLevel constants.
     * @param  string|\Stringable  $message Message, with {placeholder} tokens left unexpanded.
     * @param  array<string,mixed> $context Values that would be substituted into $message.
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * The single record written, failing loudly if there was not exactly one.
     *
     * @return array{level:string,message:string,context:array<string,mixed>}
     */
    public function onlyRecord(): array
    {
        if (count($this->records) !== 1) {
            throw new \LogicException(sprintf(
                'Expected exactly 1 log record, got %d.',
                count($this->records)
            ));
        }
        return $this->records[0];
    }
}
