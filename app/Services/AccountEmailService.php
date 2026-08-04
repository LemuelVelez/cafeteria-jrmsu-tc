<?php

namespace App\Services;

use CodeIgniter\Email\Email;

class AccountEmailService
{
    private const BRAND_NAVY = '#102A43';
    private const BRAND_BLUE = '#1D4ED8';
    private const BRAND_GOLD = '#F59E0B';
    private const BRAND_GREEN = '#0F766E';

    public function sendVerification(array $user, string $token): bool
    {
        $url = site_url('verify-email') . '?token=' . rawurlencode($token);
        $name = (string) $user['name'];

        return $this->send(
            (string) $user['email'],
            'Verify your email address',
            $this->buildMessage(
                'Verify your email address',
                "Hello {$name},",
                'Welcome to the cafeteria! Please verify your email address to activate your account and start ordering.',
                'Verify email address',
                $url,
                'This secure link expires in 24 hours.',
                'If you did not create this account, you can safely ignore this email.',
            ),
            "Hello {$name},\n\nPlease verify your email address to activate your cafeteria account:\n{$url}\n\nThis secure link expires in 24 hours. If you did not create this account, you can safely ignore this email.",
        );
    }

    public function sendPasswordReset(array $user, string $token): bool
    {
        $url = site_url('reset-password') . '?token=' . rawurlencode($token);
        $name = (string) $user['name'];

        return $this->send(
            (string) $user['email'],
            'Reset your password',
            $this->buildMessage(
                'Reset your password',
                "Hello {$name},",
                'We received a request to reset the password for your cafeteria account.',
                'Reset password',
                $url,
                'This secure link expires in one hour.',
                'If you did not request a password reset, no action is needed and your password will remain unchanged.',
            ),
            "Hello {$name},\n\nUse the link below to reset your cafeteria account password:\n{$url}\n\nThis secure link expires in one hour. If you did not request a password reset, no action is needed.",
        );
    }

    private function buildMessage(
        string $heading,
        string $greeting,
        string $intro,
        string $buttonLabel,
        string $actionUrl,
        string $expiryNotice,
        string $securityNotice,
    ): string {
        $cafeteriaName = (string) env('CAFETERIA_NAME', 'JRMSU-TC Cafeteria');
        $logoUrl = (string) env('CAFETERIA_LOGO_URL', base_url('assets/img/jrmsu-cafeteria-logo.png'));
        $safeName = esc($cafeteriaName);
        $safeLogoUrl = esc($logoUrl, 'attr');
        $safeHeading = esc($heading);
        $safeGreeting = esc($greeting);
        $safeIntro = esc($intro);
        $safeButtonLabel = esc($buttonLabel);
        $safeActionUrl = esc($actionUrl, 'attr');
        $safeExpiryNotice = esc($expiryNotice);
        $safeSecurityNotice = esc($securityNotice);
        $navy = self::BRAND_NAVY;
        $blue = self::BRAND_BLUE;
        $gold = self::BRAND_GOLD;
        $green = self::BRAND_GREEN;
        $year = date('Y');

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="color-scheme" content="light">
            <meta name="supported-color-schemes" content="light">
            <title>{$safeHeading}</title>
            <style>
                @media only screen and (max-width: 620px) {
                    .email-shell { width: 100% !important; }
                    .email-content { padding: 30px 22px !important; }
                    .email-header { padding: 28px 22px !important; }
                    .email-button { display: block !important; text-align: center !important; }
                }
            </style>
        </head>
        <body style="margin:0; padding:0; background-color:#F4F7FB; color:#243B53; font-family:Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%;">
            <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
                {$safeIntro}
            </div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#F4F7FB;">
                <tr>
                    <td align="center" style="padding:36px 14px;">
                        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" class="email-shell" style="width:600px; max-width:600px; background-color:#FFFFFF; border:1px solid #DBE4EE; border-radius:18px; overflow:hidden; box-shadow:0 12px 32px rgba(16,42,67,0.10);">
                            <tr>
                                <td height="6" style="height:6px; background-color:{$gold}; font-size:0; line-height:0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="email-header" style="padding:34px 42px; background-color:{$navy}; background-image:linear-gradient(135deg, {$navy} 0%, {$green} 100%);">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            <td width="78" valign="middle" style="width:78px;">
                                                <div style="width:62px; height:62px; border-radius:50%; background-color:#FFFFFF; border:3px solid rgba(255,255,255,0.35); overflow:hidden; text-align:center;">
                                                    <img src="{$safeLogoUrl}" width="62" height="62" alt="{$safeName} logo" style="display:block; width:62px; height:62px; object-fit:contain; border:0;">
                                                </div>
                                            </td>
                                            <td valign="middle" style="padding-left:14px;">
                                                <div style="font-size:12px; line-height:18px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#FCD34D;">Official account message</div>
                                                <div style="margin-top:4px; font-size:23px; line-height:30px; font-weight:700; color:#FFFFFF;">{$safeName}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td class="email-content" style="padding:42px;">
                                    <div style="display:inline-block; padding:6px 11px; border-radius:999px; background-color:#ECFEFF; color:{$green}; font-size:12px; line-height:16px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase;">Account security</div>
                                    <h1 style="margin:18px 0 16px; color:{$navy}; font-size:30px; line-height:38px; font-weight:700;">{$safeHeading}</h1>
                                    <p style="margin:0 0 14px; color:#334E68; font-size:16px; line-height:26px;">{$safeGreeting}</p>
                                    <p style="margin:0 0 28px; color:#526A80; font-size:16px; line-height:26px;">{$safeIntro}</p>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 28px;">
                                        <tr>
                                            <td bgcolor="{$blue}" style="border-radius:10px; background-color:{$blue};">
                                                <a href="{$safeActionUrl}" class="email-button" style="display:inline-block; padding:14px 24px; border:1px solid {$blue}; border-radius:10px; background-color:{$blue}; color:#FFFFFF; font-size:16px; line-height:20px; font-weight:700; text-decoration:none;">{$safeButtonLabel}</a>
                                            </td>
                                        </tr>
                                    </table>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:0 0 24px; border:1px solid #D9E7F5; border-radius:12px; background-color:#F8FBFF;">
                                        <tr>
                                            <td width="42" valign="top" style="width:42px; padding:17px 0 17px 18px; color:{$gold}; font-size:20px; line-height:24px;">&#9201;</td>
                                            <td style="padding:17px 18px 17px 8px; color:#435A70; font-size:14px; line-height:22px;">
                                                <strong style="color:{$navy};">Time-sensitive link</strong><br>
                                                {$safeExpiryNotice}
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0; color:#718096; font-size:13px; line-height:21px;">{$safeSecurityNotice}</p>
                                    <div style="height:1px; margin:30px 0 22px; background-color:#E3EBF3;"></div>
                                    <p style="margin:0 0 8px; color:#829AB1; font-size:12px; line-height:19px;">If the button does not work, copy and paste this link into your browser:</p>
                                    <p style="margin:0; word-break:break-all; color:{$blue}; font-size:12px; line-height:19px;">
                                        <a href="{$safeActionUrl}" style="color:{$blue}; text-decoration:underline;">{$safeActionUrl}</a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:24px 30px; border-top:1px solid #E6EDF4; background-color:#FAFCFF;">
                                    <p style="margin:0 0 5px; color:{$navy}; font-size:13px; line-height:20px; font-weight:700;">{$safeName}</p>
                                    <p style="margin:0; color:#829AB1; font-size:12px; line-height:19px;">&copy; {$year} {$safeName}. This is an automated message; please do not reply.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function send(string $recipient, string $subject, string $message, string $altMessage): bool
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
        $email->setAltMessage($altMessage);

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
