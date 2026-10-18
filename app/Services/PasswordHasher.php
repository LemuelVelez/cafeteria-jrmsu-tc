<?php
namespace App\Services;
final class PasswordHasher
{
    public static function algorithm(): string|int {return defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT;}
    public static function options(): array {return self::algorithm()===PASSWORD_BCRYPT?['cost'=>12]:[];}
    public static function hash(string $password): string {return password_hash($password,self::algorithm(),self::options());}
    public static function needsRehash(string $hash): bool {return password_needs_rehash($hash,self::algorithm(),self::options());}
}
