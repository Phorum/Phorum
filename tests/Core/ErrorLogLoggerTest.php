<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use PHPUnit\Framework\TestCase;
use Phorum\Core\ErrorLogLogger;
use Psr\Log\LogLevel;

/**
 * Covers ErrorLogLogger: the default PSR-3 logger that forwards records to
 * PHP's error_log().
 *
 * Rather than mocking error_log(), each test redirects the error_log ini
 * setting to a temporary file for the duration of the test and reads the
 * written lines back. The original value is restored in tearDown, so no
 * global state leaks into other tests.
 */
class ErrorLogLoggerTest extends TestCase
{
    /** Temporary file receiving error_log() output during a test. */
    private string $logFile;

    /** The error_log ini value in effect before the test redirected it. */
    private string|false $originalErrorLog;

    /** Redirects error_log() to a temporary file. */
    protected function setUp(): void
    {
        $this->logFile          = tempnam(sys_get_temp_dir(), 'error_log_logger_');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->logFile);
    }

    /** Restores the previous error_log setting and removes the temporary file. */
    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog === false ? '' : $this->originalErrorLog);
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    /**
     * Everything written to the temporary log so far, one entry per line with
     * PHP's leading timestamp stripped.
     *
     * @return list<string>
     */
    private function loggedLines(): array
    {
        $lines = [];
        foreach (file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $lines[] = preg_replace('/^\[[^\]]+\] /', '', $line);
        }
        return $lines;
    }

    /** A plain message is written once, prefixed with its uppercased level. */
    public function testLogWritesMessagePrefixedWithLevel(): void
    {
        (new ErrorLogLogger())->log(LogLevel::WARNING, 'something drifted');

        $this->assertSame(['[WARNING] something drifted'], $this->loggedLines());
    }

    /** The PSR-3 level helpers route through log() with their own level. */
    public function testLevelHelpersRecordTheirOwnLevel(): void
    {
        $logger = new ErrorLogLogger();
        $logger->error('bad thing');
        $logger->info('ordinary thing');

        $this->assertSame(
            ['[ERROR] bad thing', '[INFO] ordinary thing'],
            $this->loggedLines()
        );
    }

    /** {placeholder} tokens are replaced by the matching context values. */
    public function testLogInterpolatesContextPlaceholders(): void
    {
        (new ErrorLogLogger())->warning(
            'patch {patch} skipped ({error})',
            ['patch' => 7, 'error' => "Duplicate column name 'color'"]
        );

        $this->assertSame(
            ["[WARNING] patch 7 skipped (Duplicate column name 'color')"],
            $this->loggedLines()
        );
    }

    /** A context value with no matching token leaves the message untouched. */
    public function testLogLeavesMessageUnchangedWhenContextDoesNotMatch(): void
    {
        (new ErrorLogLogger())->notice('nothing to substitute', ['unused' => 'value']);

        $this->assertSame(['[NOTICE] nothing to substitute'], $this->loggedLines());
    }

    /**
     * A context value that cannot be rendered as a string (here an array) is
     * skipped, leaving its placeholder in place rather than raising an error.
     */
    public function testLogLeavesPlaceholderForUnstringableContextValue(): void
    {
        (new ErrorLogLogger())->warning('got {value}', ['value' => ['a', 'b']]);

        $this->assertSame(['[WARNING] got {value}'], $this->loggedLines());
    }

    /** A Stringable context value is rendered via its __toString(). */
    public function testLogInterpolatesStringableContextValue(): void
    {
        $value = new class implements \Stringable {
            public function __toString(): string
            {
                return 'rendered';
            }
        };

        (new ErrorLogLogger())->info('got {value}', ['value' => $value]);

        $this->assertSame(['[INFO] got rendered'], $this->loggedLines());
    }

    /** A Stringable message is accepted and written as its string form. */
    public function testLogAcceptsStringableMessage(): void
    {
        $message = new class implements \Stringable {
            public function __toString(): string
            {
                return 'from an object';
            }
        };

        (new ErrorLogLogger())->debug($message);

        $this->assertSame(['[DEBUG] from an object'], $this->loggedLines());
    }
}
