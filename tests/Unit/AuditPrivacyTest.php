<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use PHPUnit\Framework\TestCase;

final class AuditPrivacyTest extends TestCase
{
    public function testPrivateFieldsAndUnstructuredCustomerTextNeverEnterAuditDetails(): void
    {
        $details = [
            'status' => 'completed', 'quantity' => 2,
            'name' => 'Private User Name',
            'email' => 'private@example.invalid',
            'customer_address' => 'Private Street 42',
            'reason' => 'My access code is 1234',
            'description' => 'Deliver to my apartment',
            'comment' => 'Contact private@example.invalid',
            'note' => 'My personal phone number',
            'supplier' => 'Private contact detail',
            'changes' => [
                'password_hash' => ['before' => 'secret', 'after' => 'new secret'],
                'notes' => ['before' => 'old details', 'after' => 'new details'],
                'stock' => ['before' => 2, 'after' => 4],
            ],
        ];
        $clean = AuditLogService::scrub($details);
        self::assertSame('completed', $clean['status']);
        self::assertSame(2, $clean['quantity']);
        self::assertSame(['before' => 2, 'after' => 4], $clean['changes']['stock']);
        foreach (['name','email','customer_address','reason','description','comment','note','supplier'] as $key) {
            self::assertArrayNotHasKey($key, $clean);
        }
        self::assertArrayNotHasKey('password_hash', $clean['changes']);
        self::assertArrayNotHasKey('notes', $clean['changes']);
        self::assertStringNotContainsString('private@example.invalid', json_encode($clean, JSON_THROW_ON_ERROR));
    }
}
