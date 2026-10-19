<?php

namespace App\Services;

use CodeIgniter\Encryption\Exceptions\EncryptionException;

/**
 * Encrypted personal columns are prefixed for idempotent migrations and legacy reads.
 * Strict reads still fail when the original key is unavailable; only display reads
 * mask unreadable values so an unrelated page cannot crash or leak ciphertext.
 */
final class FieldEncryption
{
    private const PREFIX = 'enc:v1:';

    // Never substitute an empty secret: authentication must still require 2FA.
    public const UNREADABLE_TOTP_SECRET = '[encrypted-totp-unavailable]';

    private static bool $warningLogged = false;

    public static function seal(?string $value): ?string
    {
        if ($value === null || $value === '' || self::isSealed($value)) {
            return $value;
        }

        return self::PREFIX . base64_encode(service('encrypter')->encrypt($value));
    }

    public static function isSealed(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }

    /** Strict decryption for exports, migrations and other operations needing real data. */
    public static function open(?string $value): ?string
    {
        if (! self::isSealed($value)) {
            return $value;
        }

        $ciphertext = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($ciphertext === false) {
            throw new \UnexpectedValueException('Invalid encrypted field encoding.');
        }

        return service('encrypter')->decrypt($ciphertext);
    }

    /** A missing/wrong key must not crash profile, order or dashboard reads. */
    public static function openForDisplay(?string $value): ?string
    {
        try {
            return self::open($value);
        } catch (EncryptionException|\UnexpectedValueException $exception) {
            self::warnOfUnreadableData();
            return null;
        }
    }

    /** Preserve the fact that 2FA is enabled even if its secret cannot be read. */
    public static function openTotp(?string $value): ?string
    {
        try {
            return self::open($value);
        } catch (EncryptionException|\UnexpectedValueException $exception) {
            self::warnOfUnreadableData();
            return self::UNREADABLE_TOTP_SECRET;
        }
    }

    private static function warnOfUnreadableData(): void
    {
        if (self::$warningLogged) {
            return;
        }
        self::$warningLogged = true;
        log_message('error', 'Encrypted fields could not be authenticated. Restore the original encryption.key (or writable/.encryption-key); stored ciphertext has not been modified.');
    }
}
