<?php

namespace Tests\Database;

use App\Services\WebPushService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** MySQL-backed transport-mock tests; never reach a real push provider. */
#[Group('database')]
final class WebPushDispatchDatabaseTest extends DatabaseTestCase
{
    private function fixture(): array
    {
        $db = db_connect('tests');
        $userId = $this->makeCustomer();
        $db->table('settings')->where('setting_key', 'web_push_enabled')
            ->update(['setting_value' => '1']);
        $push = new WebPushService($db);
        // Valid 65-byte uncompressed P-256 public key-shaped data. No HTTP
        // encryption is performed because transport responses are injected.
        $keys = [
            'p256dh' => rtrim(strtr(base64_encode("\x04" . str_repeat("\x02", 64)), '+/', '-_'), '='),
            'auth' => rtrim(strtr(base64_encode(str_repeat("\x01", 16)), '+/', '-_'), '='),
        ];
        $one = 'https://fcm.googleapis.com/fcm/send/fixture-one';
        $two = 'https://fcm.googleapis.com/fcm/send/fixture-two';
        $push->subscribe($userId, ['endpoint' => $one, 'keys' => $keys]);
        $push->subscribe($userId, ['endpoint' => $two, 'keys' => $keys]);
        $db->table('notifications')->insert([
            'user_id' => $userId, 'order_id' => null, 'type' => 'order_status',
            'title' => 'Prepared', 'message' => 'Your order is ready.',
            'link' => '/customer/orders', 'read_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $notification = (int) $db->insertID();
        $push->enqueue($userId, $notification);
        return [$db, $notification, $one, $two];
    }

    public function testPartialFailureRetriesOnlyUnacknowledgedDeviceAndAuditsEachAttempt(): void
    {
        [$db, $notification, $one, $two] = $this->fixture();
        $seen = [];
        $transport = static function (array $subscription, string $json) use (&$seen, $one): int {
            $seen[] = $subscription['endpoint'];
            self::assertSame('Prepared', json_decode($json, true)['title']);
            return $subscription['endpoint'] === $one || count($seen) > 2 ? 201 : 503;
        };
        $service = new WebPushService($db, $transport);
        self::assertSame(['sent' => 0, 'failed' => 1], $service->flush());
        self::assertSame(1, $db->table('push_delivery_receipts')
            ->where('notification_id', $notification)->countAllResults());
        self::assertSame(['sent' => 1, 'failed' => 0], $service->flush());
        self::assertSame([$one, $two, $two], $seen);
        self::assertSame(2, $db->table('push_delivery_receipts')
            ->where('notification_id', $notification)->countAllResults());
        $outbox = $db->table('push_outbox')->where('notification_id', $notification)->get()->getRowArray();
        self::assertSame(2, (int) $outbox['attempts']);
        self::assertNotEmpty($outbox['delivered_at']);
        self::assertSame(['sent' => 0, 'failed' => 0], $service->flush());
        self::assertCount(3, $seen);
        self::assertSame(2, $db->table('audit_logs')->where([
            'action' => 'push_dispatch_attempt', 'entity_type' => 'notification', 'entity_id' => $notification,
        ])->countAllResults());
    }

    public function testGoneSubscriptionsArePrunedAndAlreadySentDevicesAreNotRepeated(): void
    {
        [$db, $notification, $one, $two] = $this->fixture();
        $seen = [];
        $service = new WebPushService($db, static function (array $sub) use (&$seen, $one): int {
            $seen[] = $sub['endpoint'];
            return $sub['endpoint'] === $one ? 410 : 404;
        });
        self::assertSame(['sent' => 1, 'failed' => 0], $service->flush());
        self::assertSame(0, $db->table('push_subscriptions')->countAllResults());
        self::assertCount(2, $seen);
        self::assertSame(['sent' => 0, 'failed' => 0], $service->flush());
        $audit = $db->table('audit_logs')->where([
            'action' => 'push_dispatch_attempt', 'entity_id' => $notification,
        ])->get()->getRowArray();
        $details = json_decode((string) $audit['details_json'], true);
        self::assertSame(2, $details['retired_devices']);
        self::assertArrayNotHasKey('endpoint', $details);
    }
}
