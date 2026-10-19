<?php

namespace App\Services;

/** API responses must remain JSON, including under a subdirectory deployment. */
final class ApiRequestPolicy
{
    public static function isApiPath(string $path): bool
    {
        // Both /api/orders and /cafeteria/index.php/api/orders are possible.
        // Match a whole path segment, never a similar name such as /apiculture.
        return preg_match('~(?:^|/)api(?:/|$)~', $path) === 1;
    }
}
