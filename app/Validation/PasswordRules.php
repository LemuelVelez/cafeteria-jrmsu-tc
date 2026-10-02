<?php

namespace App\Validation;

class PasswordRules
{
    private const COMMON = [
        'password', 'password123', 'password123!', '1234567890', 'qwerty12345',
        'admin12345', 'letmein123!', 'welcome123!', 'iloveyou123', 'cafeteria123!',
    ];

    public function strong_password(string $value, ?string $params = null, array $data = [], ?string &$error = null): bool
    {
        $password = (string) $value;
        $checks = [
            mb_strlen($password) >= 10,
            preg_match('/[A-Z]/u', $password) === 1,
            preg_match('/[a-z]/u', $password) === 1,
            preg_match('/\d/u', $password) === 1,
            preg_match('/[^A-Za-z0-9]/u', $password) === 1,
        ];
        if (in_array(false, $checks, true)) {
            $error = 'Password must be at least 10 characters and include uppercase, lowercase, a number, and a symbol.';
            return false;
        }

        $normalized = mb_strtolower($password);
        if (in_array($normalized, self::COMMON, true)) {
            $error = 'Choose a less common password.';
            return false;
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $localPart = $email !== '' ? strstr($email, '@', true) : '';
        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));
        $needles = array_filter([$localPart, $name], static fn ($item): bool => is_string($item) && mb_strlen(trim($item)) >= 3);
        foreach ($needles as $needle) {
            $needle = preg_replace('/\s+/u', '', (string) $needle) ?? '';
            if ($needle !== '' && str_contains(preg_replace('/\s+/u', '', $normalized) ?? $normalized, $needle)) {
                $error = 'Password must not contain your name or email username.';
                return false;
            }
        }

        return true;
    }
}
