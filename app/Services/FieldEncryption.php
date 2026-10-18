<?php
namespace App\Services;
/** Encrypted personal columns are prefixed to allow idempotent migrations and legacy reads. */
final class FieldEncryption
{
    private const PREFIX='enc:v1:';
    public static function seal(?string $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with($value,self::PREFIX)) return $value;
        return self::PREFIX.base64_encode(service('encrypter')->encrypt($value));
    }
    public static function open(?string $value): ?string
    {
        if ($value === null || !str_starts_with($value,self::PREFIX)) return $value;
        return service('encrypter')->decrypt(base64_decode(substr($value,strlen(self::PREFIX)),true));
    }
}
