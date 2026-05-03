<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Email service — SMTP via PHPMailer.
 * Mirrors server/src/lib/email.ts (verifyTransport + sendEmail).
 */
class EmailService
{
    public function verifyTransport(array $cfg): array
    {
        $mailer = $this->buildMailer($cfg);
        try {
            $mailer->smtpConnect();
            $mailer->smtpClose();
            return ['ok' => true];
        } catch (MailerException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function send(array $cfg, array $message): array
    {
        $mailer = $this->buildMailer($cfg);
        try {
            $to = is_array($message['to']) ? $message['to'] : [$message['to']];
            foreach ($to as $addr) {
                $mailer->addAddress($addr);
            }
            $mailer->Subject = $message['subject'];
            if (!empty($message['html'])) {
                $mailer->isHTML(true);
                $mailer->Body    = $message['html'];
                $mailer->AltBody = $message['text'] ?? strip_tags($message['html']);
            } else {
                $mailer->Body = $message['text'] ?? '';
            }
            $mailer->send();
            return ['ok' => true];
        } catch (MailerException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildMailer(array $cfg): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $cfg['host'];
        $mailer->Port       = (int) ($cfg['port'] ?? 587);
        $mailer->SMTPSecure = ($cfg['secure'] ?? false) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        if (!empty($cfg['user'])) {
            $mailer->SMTPAuth = true;
            $mailer->Username = $cfg['user'];
            $mailer->Password = $cfg['password'] ?? '';
        }
        $mailer->setFrom($cfg['fromAddress'], $cfg['fromName'] ?? '');
        $mailer->Timeout = 10;
        return $mailer;
    }
}
