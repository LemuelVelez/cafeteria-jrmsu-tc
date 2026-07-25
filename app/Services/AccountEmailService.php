<?php

namespace App\Services;

use CodeIgniter\Email\Email;

class AccountEmailService
{
    public function sendVerification(array $user, string $token): bool
    {
        $url = site_url('verify-email') . '?token=' . rawurlencode($token);
        $name = esc((string) $user['name']);
        $safeUrl = esc($url, 'attr');

        return $this->send(
            (string) $user['email'],
            'Verify your email address',
            <<<HTML
            <p>Hello {$name},</p>
            <p>Please verify your email address to activate your cafeteria account.</p>
            <p><a href="{$safeUrl}">Verify email address</a></p>
            <p>This link expires in 24 hours. If you did not create this account, you can ignore this email.</p>
            HTML,
        );
    }

    public function sendPasswordReset(array $user, string $token): bool
    {
        $url = site_url('reset-password') . '?token=' . rawurlencode($token);
        $name = esc((string) $user['name']);
        $safeUrl = esc($url, 'attr');

        return $this->send(
            (string) $user['email'],
            'Reset your password',
            <<<HTML
            <p>Hello {$name},</p>
            <p>We received a request to reset your cafeteria account password.</p>
            <p><a href="{$safeUrl}">Reset password</a></p>
            <p>This link expires in one hour. If you did not request a password reset, you can ignore this email.</p>
            HTML,
        );
    }

    private function send(string $recipient, string $subject, string $message): bool
    {
        $gmailUser = trim((string) env('GMAIL_USER', ''));
        $gmailPassword = preg_replace('/\s+/', '', (string) env('GMAIL_APP_PASSWORD', '')) ?? '';

        if ($gmailUser === '' || $gmailPassword === '') {
            log_message('error', 'Account email could not be sent because GMAIL_USER or GMAIL_APP_PASSWORD is missing.');
            return false;
        }

        /** @var Email $email */
        $email = service('email');
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => 'smtp.gmail.com',
            'SMTPUser' => $gmailUser,
            'SMTPPass' => $gmailPassword,
            'SMTPPort' => 587,
            'SMTPCrypto' => 'tls',
            'SMTPTimeout' => 15,
            'mailType' => 'html',
            'charset' => 'UTF-8',
            'newline' => "\r\n",
            'CRLF' => "\r\n",
        ]);
        $email->clear(true);
        $email->setFrom($gmailUser, (string) env('CAFETERIA_NAME', 'JRMSU-TC Cafeteria'));
        $email->setTo($recipient);
        $email->setSubject($subject);
        $email->setMessage($message);

        if ($email->send()) {
            return true;
        }

        log_message('error', 'Account email delivery failed for {recipient}: {debug}', [
            'recipient' => $recipient,
            'debug' => strip_tags($email->printDebugger(['headers'])),
        ]);

        return false;
    }
}
