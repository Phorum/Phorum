<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\AdminSecret;
use Phorum\Core\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the guard that refuses to sign admin cookies with a secret anyone
 * could guess. The admin-session and impersonation cookies carry no
 * server-side state, so the secret is the only thing preventing a forged
 * admin session — and etc/phorum.example.php used to ship a working one.
 */
class AdminSecretTest extends TestCase
{
    /** Build a Config whose admin_secret is $secret. */
    private function configWith(?string $secret): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'admin_secret' ? $secret : $default
        );
        return $config;
    }

    /**
     * Secrets that must be refused: unset, the shipped placeholders and
     * reworded variants of them, and anything too short to be a credible
     * HMAC key.
     *
     * @param ?string $secret The configured admin_secret.
     */
    #[DataProvider('unusableSecretProvider')]
    public function testUnusableSecretsAreRejected(?string $secret): void
    {
        $config = $this->configWith($secret);

        $this->assertFalse(AdminSecret::isUsable($config));
        $this->assertNotNull(AdminSecret::problem($config));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function unusableSecretProvider(): array
    {
        return [
            'null'                  => [null],
            'empty'                 => [''],
            'shipped placeholder'   => ['change-me-to-a-long-random-string'],
            'docs placeholder'      => ['replace-with-a-long-random-string'],
            'placeholder uppercase' => ['CHANGE-ME-TO-A-LONG-RANDOM-STRING'],
            'placeholder embedded'  => ['prefix-change-me-suffix-padded-out-to-full-length'],
            'too short'             => ['abc123'],
            'one under minimum'     => [str_repeat('a', AdminSecret::MIN_LENGTH - 1)],
        ];
    }

    /** A secret of the documented shape is accepted. */
    public function testGeneratedSecretIsAccepted(): void
    {
        $config = $this->configWith(bin2hex(random_bytes(32)));

        $this->assertTrue(AdminSecret::isUsable($config));
        $this->assertNull(AdminSecret::problem($config));
    }

    /** Exactly the minimum length is enough. */
    public function testSecretAtMinimumLengthIsAccepted(): void
    {
        $this->assertTrue(AdminSecret::isUsable($this->configWith(str_repeat('x', AdminSecret::MIN_LENGTH))));
    }

    /** get() hands back the secret unchanged when it's usable. */
    public function testGetReturnsTheConfiguredSecret(): void
    {
        $secret = bin2hex(random_bytes(32));

        $this->assertSame($secret, AdminSecret::get($this->configWith($secret)));
    }

    /** get() is the backstop for callers that sign without checking first. */
    public function testGetThrowsOnAnUnusableSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('admin_secret');

        AdminSecret::get($this->configWith('change-me-to-a-long-random-string'));
    }

    /** The message names the setting and the file, so it's actionable. */
    public function testProblemMessageIsActionable(): void
    {
        $problem = AdminSecret::problem($this->configWith('change-me-to-a-long-random-string'));

        $this->assertStringContainsString('admin_secret', (string) $problem);
        $this->assertStringContainsString('etc/phorum.php', (string) $problem);
        $this->assertStringContainsString('random_bytes', (string) $problem);
    }
}
