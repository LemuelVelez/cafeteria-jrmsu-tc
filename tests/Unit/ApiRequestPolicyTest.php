<?php

namespace Tests\Unit;

use App\Services\ApiRequestPolicy;
use PHPUnit\Framework\TestCase;

final class ApiRequestPolicyTest extends TestCase
{
    public function testApiRoutesAreDetectedWithOrWithoutLeadingSlashAndBasePath(): void
    {
        foreach ([
            '/api/orders', 'api/orders', '/api', '/cafeteria/api/orders',
            '/cafeteria/index.php/api/users/5/status',
        ] as $path) {
            self::assertTrue(ApiRequestPolicy::isApiPath($path), $path);
        }
    }

    public function testUnrelatedRoutesCannotBeMistakenForTheApi(): void
    {
        foreach (['/', '/menu', '/customer/orders', '/apiculture', '/cafeteria/apiv2/orders',
            '/cafeteria/capi/orders'] as $path) {
            self::assertFalse(ApiRequestPolicy::isApiPath($path), $path);
        }
    }
}
