<?php

namespace Tests\Unit;

use App\Services\CustomerDataExportService;
use PHPUnit\Framework\TestCase;

final class CustomerPrivacyProjectionTest extends TestCase
{
    public function testExportOnlyIncludesExplicitlyPermittedFields(): void
    {
        $record = [
            'id' => 8, 'order_number' => 'ORDER-8', 'order_type' => 'delivery',
            'total' => '109.00', 'delivery_address' => 'Test Street',
            'request_token' => 'private-request-token',
            'pickup_code_hash' => 'never-export-pickup-hash',
            'delivery_code_hash' => 'never-export-delivery-hash',
            'handoff_verified_by' => 99, 'customer_id' => 17,
        ];
        $order = CustomerDataExportService::project($record, 'orders');
        self::assertSame('ORDER-8', $order['order_number']);
        self::assertSame('Test Street', $order['delivery_address']);
        foreach (['request_token', 'pickup_code_hash', 'delivery_code_hash',
            'handoff_verified_by', 'customer_id'] as $private) {
            self::assertArrayNotHasKey($private, $order);
        }
    }

    public function testExportDoesNotIncludePasswordTotpOrPaymentReferences(): void
    {
        $profile = CustomerDataExportService::project([
            'name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '09990000000',
            'password_hash' => 'super-private-hash', 'totp_secret' => 'totp-secret',
            'session_version' => 23, 'notification_email_prefs' => '{}',
        ], 'profile');
        self::assertSame('09990000000', $profile['phone']);
        self::assertEqualsCanonicalizing(['name', 'email', 'phone'], array_keys($profile));

        $payment = CustomerDataExportService::project([
            'order_id' => 11, 'amount' => '250.00', 'status' => 'paid',
            'transaction_reference' => 'private-merchant-reference',
        ], 'payments');
        self::assertSame('250.00', $payment['amount']);
        self::assertArrayNotHasKey('transaction_reference', $payment);
    }

    public function testItemsIncludePurchaseDetailsButNotInternalCosts(): void
    {
        $item = CustomerDataExportService::project([
            'order_id' => 11, 'product_name' => 'Rice Meal',
            'addons_json' => '[{"name":"Egg","price":12}]',
            'unit_cost' => '17.50', 'cost_status' => 'complete',
        ], 'items');
        self::assertSame([['name' => 'Egg', 'price' => 12]], $item['addons']);
        self::assertArrayNotHasKey('addons_json', $item);
        self::assertArrayNotHasKey('unit_cost', $item);
        self::assertArrayNotHasKey('cost_status', $item);
    }

    public function testUnknownExportSectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CustomerDataExportService::project(['secret' => 'no'], 'internal');
    }
}
