<?php

namespace Tests\Database;

use App\Services\CustomerDataDeletionService;
use App\Services\CustomerDataExportService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Exercises actual MySQL ownership, audit, and anonymization on the test schema. */
#[Group('database')]
final class CustomerPrivacyDatabaseTest extends DatabaseTestCase
{
    private function fixtureOrder(int $customerId, string $suffix): int
    {
        $db = db_connect('tests');
        $db->table('orders')->insert([
            'order_number' => 'PRIV-' . $suffix,
            'customer_id' => $customerId,
            'order_type' => 'delivery',
            'status' => 'delivered',
            'subtotal' => '118.00', 'total' => '120.00', 'delivery_fee' => '2.00',
            'payment_method' => 'cash_on_delivery', 'payment_status' => 'paid',
            'delivery_address' => 'Private customer address ' . $suffix,
            'notes' => 'Customer door code ' . $suffix,
            'request_token' => 'private-' . $suffix,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $orderId = (int) $db->insertID();
        $productId = (int) $db->table('products')->select('id')->get(1)->getRow('id');
        $db->table('order_items')->insert([
            'order_id' => $orderId, 'product_id' => $productId,
            'product_name' => 'Privacy fixture meal', 'quantity' => 1,
            'unit_price' => '118.00', 'line_total' => '118.00',
            'notes' => 'Private dietary request ' . $suffix,
        ]);
        $db->table('payments')->insert([
            'order_id' => $orderId, 'method' => 'cash_on_delivery',
            'amount' => '120.00', 'status' => 'paid',
            'transaction_reference' => 'INTERNAL-PAYMENT-' . $suffix,
        ]);
        $db->table('order_status_history')->insert([
            'order_id' => $orderId, 'to_status' => 'delivered',
            'note' => 'Gate directions ' . $suffix,
        ]);
        return $orderId;
    }

    public function testExportContainsOnlyOwnedOrdersAndAuditsOnce(): void
    {
        $db = db_connect('tests');
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();
        $mine = $this->fixtureOrder($customer, bin2hex(random_bytes(5)));
        $other = $this->fixtureOrder($otherCustomer, bin2hex(random_bytes(5)));
        $export = (new CustomerDataExportService($db))->export($customer);
        self::assertNotNull($export);
        $ids = array_map('intval', array_column($export['orders'], 'id'));
        self::assertContains($mine, $ids);
        self::assertNotContains($other, $ids);
        self::assertCount(1, $export['payments']);
        self::assertCount(1, $export['items']);
        self::assertArrayNotHasKey('request_token', $export['orders'][0]);
        self::assertArrayNotHasKey('transaction_reference', $export['payments'][0]);
        self::assertSame(1, $db->table('audit_logs')->where([
            'user_id' => $customer, 'action' => 'personal_data_export',
        ])->countAllResults());
        self::assertSame(null, (new CustomerDataExportService($db))->export(999999999));
    }

    public function testApprovalScrubsPersonalFieldsButKeepsFinancialRecordsAndOneAudit(): void
    {
        $db = db_connect('tests');
        $customer = $this->makeCustomer(['deletion_requested_at' => date('Y-m-d H:i:s')]);
        $otherCustomer = $this->makeCustomer();
        $mine = $this->fixtureOrder($customer, bin2hex(random_bytes(5)));
        $other = $this->fixtureOrder($otherCustomer, bin2hex(random_bytes(5)));
        $db->table('reviews')->insert([
            'order_id' => $mine, 'customer_id' => $customer,
            'rating' => 4, 'comment' => 'Private review',
        ]);
        $db->table('notifications')->insert([
            'user_id' => $customer, 'order_id' => $mine,
            'type' => 'order_status', 'title' => 'Delivery',
            'message' => 'Private customer instructions',
        ]);
        $db->table('push_subscriptions')->insert([
            'user_id' => $customer, 'endpoint_hash' => hash('sha256', 'fixture-' . $customer),
            'subscription_cipher' => 'sensitive-device-token', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        (new CustomerDataDeletionService($db))->approve($customer);
        $user = $db->table('users')->where('id', $customer)->get()->getRowArray();
        self::assertSame('inactive', $user['status']);
        self::assertNull($user['phone']);
        self::assertNull($user['address']);
        self::assertNull($user['deletion_requested_at']);
        self::assertGreaterThan(1, (int) $user['session_version']);
        self::assertSame('120.00', $db->table('orders')->where('id', $mine)->get()->getRow('total'));
        self::assertNull($db->table('orders')->where('id', $mine)->get()->getRow('notes'));
        self::assertNull($db->table('order_items')->where('order_id', $mine)->get()->getRow('notes'));
        self::assertNull($db->table('order_status_history')->where('order_id', $mine)->get()->getRow('note'));
        self::assertNull($db->table('reviews')->where('customer_id', $customer)->get()->getRow('comment'));
        self::assertSame(0, $db->table('notifications')->where('user_id', $customer)->countAllResults());
        self::assertSame(0, $db->table('push_subscriptions')->where('user_id', $customer)->countAllResults());
        self::assertNotNull($db->table('orders')->where('id', $other)->get()->getRow('notes'));
        self::assertSame(1, $db->table('audit_logs')->where([
            'entity_type' => 'user', 'entity_id' => $customer,
            'action' => 'personal_data_deletion_approved',
        ])->countAllResults());
        $this->expectException(\DomainException::class);
        (new CustomerDataDeletionService($db))->approve($customer);
    }
}
