<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Service\MailService;
use PHPUnit\Framework\TestCase;

/** Records the mailer's property state at send() time instead of hitting the network. */
class RecordingPHPMailer extends PHPMailer
{
    public bool $sendCalled = false;

    public function send(): bool
    {
        $this->sendCalled = true;
        return true;
    }
}

class MailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeConfig(array $overrides = []): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $overrides[$key] ?? $default
        );
        return $config;
    }

    public function testSendReturnsFalseWhenMailHostNotConfigured(): void
    {
        $service = new MailService($this->makeConfig());
        $result  = $service->send('to@example.com', 'To Name', 'Subject', 'Body');
        $this->assertFalse($result);
    }

    public function testSendConfiguresAuthWhenUsernameIsSet(): void
    {
        $recorder = new RecordingPHPMailer(exceptions: false);
        $config   = $this->makeConfig([
            'mail_host'       => 'smtp.example.com',
            'mail_port'       => 587,
            'mail_from'       => 'noreply@example.com',
            'mail_username'   => 'smtp-user',
            'mail_password'   => 'smtp-pass',
            'mail_encryption' => 'tls',
        ]);

        $service = new MailService($config, fn() => $recorder);
        $result  = $service->send('to@example.com', 'To Name', 'Subject', 'Body');

        $this->assertTrue($result);
        $this->assertTrue($recorder->sendCalled);
        $this->assertSame('smtp.example.com', $recorder->Host);
        $this->assertSame(587, $recorder->Port);
        $this->assertTrue($recorder->SMTPAuth);
        $this->assertSame('smtp-user', $recorder->Username);
        $this->assertSame('smtp-pass', $recorder->Password);
        $this->assertSame('tls', $recorder->SMTPSecure);
    }

    public function testSendLeavesAuthDisabledWhenNoUsernameConfigured(): void
    {
        $recorder = new RecordingPHPMailer(exceptions: false);
        $config   = $this->makeConfig([
            'mail_host' => 'smtp.example.com',
            'mail_port' => 25,
            'mail_from' => 'noreply@example.com',
        ]);

        $service = new MailService($config, fn() => $recorder);
        $service->send('to@example.com', 'To Name', 'Subject', 'Body');

        $this->assertFalse($recorder->SMTPAuth);
        $this->assertSame('', $recorder->Username);
        $this->assertSame('', $recorder->Password);
        $this->assertSame('', $recorder->SMTPSecure);
    }

    public function testSendUsesSslEncryption(): void
    {
        $recorder = new RecordingPHPMailer(exceptions: false);
        $config   = $this->makeConfig([
            'mail_host'       => 'smtp.example.com',
            'mail_port'       => 465,
            'mail_encryption' => 'ssl',
        ]);

        $service = new MailService($config, fn() => $recorder);
        $service->send('to@example.com', 'To Name', 'Subject', 'Body');

        $this->assertSame('ssl', $recorder->SMTPSecure);
    }
}
