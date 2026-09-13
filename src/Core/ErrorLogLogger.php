<?php
declare(strict_types=1);

namespace Phorum\Core;

use Psr\Log\AbstractLogger;

/**
 * The default PSR-3 logger: forwards every record to PHP's error_log().
 *
 * Phorum has no logging stack of its own. Under the web SAPI, error_log()
 * lands in whatever log the host is already configured to write, which is
 * where an operator expects to find these messages — so this preserves the
 * behavior the code had when it called error_log() directly.
 *
 * The reason for injecting a logger rather than keeping those direct calls
 * is testability: under the CLI SAPI error_log() writes to stderr, so a test
 * exercising a failure branch would dump its log line into the middle of the
 * test run. Passing a spy in tests captures the record instead, and lets a
 * test assert the failure was actually reported.
 */
class ErrorLogLogger extends AbstractLogger
{
    /**
     * Write one record to the PHP error log, prefixed with its level —
     * error_log() has no level concept of its own.
     *
     * @param  mixed               $level   One of the Psr\Log\LogLevel constants.
     * @param  string|\Stringable  $message Message, optionally containing {placeholder} tokens.
     * @param  array<string,mixed> $context Values substituted into $message's placeholders.
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        error_log(sprintf(
            '[%s] %s',
            strtoupper((string) $level),
            $this->interpolate((string) $message, $context)
        ));
    }

    /**
     * Replace {key} tokens in $message with the matching $context values, per
     * the PSR-3 interpolation rules. Context values that cannot be rendered as
     * a string are skipped, leaving their placeholder in the message.
     *
     * @param  string              $message Message containing {placeholder} tokens.
     * @param  array<string,mixed> $context Replacement values keyed by placeholder name.
     * @return string The message with every resolvable placeholder substituted.
     */
    protected function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if ($value === null || is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return $replacements === [] ? $message : strtr($message, $replacements);
    }
}
