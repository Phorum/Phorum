<?php
declare(strict_types=1);

namespace Phorum\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Phorum\Core\Config;

class MailService
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Send a plain-text email. Returns false and stays silent when mail_host
     * is not configured, so callers can proceed without a mail server.
     */
    public function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $body,
    ): bool {
        $host = (string) $this->config->get('mail_host', '');
        if ($host === '') {
            return false;
        }

        $fromAddress = (string) $this->config->get('mail_from', '');
        $fromName    = (string) $this->config->get('site_name', 'Phorum');

        $mailData = phorum_api_hook('mail_send', [
            'to_address'   => $toAddress,
            'to_name'      => $toName,
            'subject'      => $subject,
            'body'         => $body,
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
            'send'         => true,
        ]);

        if (!is_array($mailData) || !($mailData['send'] ?? true)) {
            return false;
        }

        $mailer = new PHPMailer(exceptions: false);
        $mailer->isSMTP();
        $mailer->Host       = $host;
        $mailer->Port       = (int) $this->config->get('mail_port', 25);
        $mailer->SMTPAuth   = false;
        $mailer->CharSet    = PHPMailer::CHARSET_UTF8;

        if ($mailData['from_address'] !== '') {
            $mailer->setFrom($mailData['from_address'], $mailData['from_name']);
        }

        $mailer->addAddress($mailData['to_address'], $mailData['to_name']);
        $mailer->Subject = $mailData['subject'];
        $mailer->Body    = $mailData['body'];
        $mailer->isHTML(false);

        return $mailer->send();
    }
}
