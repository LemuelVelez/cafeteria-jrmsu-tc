<?php

namespace Tests\Database;

use App\Services\OrderService;
use App\Services\QueueFeedService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class QueueAndMenuImagesDatabaseTest extends DatabaseTestCase
{
    private function createPickup(array $actor, string $type = 'pickup'): array
    {
        $id = (int) db_connect('tests')->table('products')->where('slug', 'crispy-fries')->get()->getRow('id');
        return $this->placeOrder([
            'items' => [['product_id' => $id, 'quantity' => 1]],
            'order_type' => $type,
            'delivery_address' => $type === 'delivery' ? 'Campus test location' : '',
            'request_token' => bin2hex(random_bytes(16)),
        ], $actor);
    }

    public function testTodayAllocationsAndNewDayRestart(): void
    {
        $db = db_connect('tests');
        $actor = $this->actor('cashier');
        $first = $this->createPickup($actor);
        $second = $this->createPickup($actor, 'delivery');
        self::assertSame((int) $first['queue_number'] + 1, (int) $second['queue_number']);
        self::assertSame($first['queue_date'], $second['queue_date']);
        $tomorrow = (new \DateTimeImmutable($first['queue_date']))->modify('+1 day')->format('Y-m-d');
        $db->transBegin();
        try {
            self::assertSame(1, OrderService::allocateQueueNumber($db, $tomorrow, $tomorrow . ' 08:00:00'));
            self::assertSame(2, OrderService::allocateQueueNumber($db, $tomorrow, $tomorrow . ' 08:01:00'));
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function testReadyStatusAndPublicFeedFilters(): void
    {
        $db = db_connect('tests');
        $actor = $this->actor('cashier');
        $ready = $this->createPickup($actor);
        $pending = $this->createPickup($actor);
        $delivery = $this->createPickup($actor, 'delivery');
        $db->table('orders')->where('id', $pending['id'])->update(['status' => 'pending']);
        $items = $db->table('order_items')->where('order_id', $ready['id'])->get()->getResultArray();
        foreach ($items as $line) $db->table('order_items')->where('id', $line['id'])->update(['prepared_at' => date('Y-m-d H:i:s')]);
        (new OrderService())->updateStatus((int) $ready['id'], 'preparing', $actor);
        $updated = (new OrderService())->updateStatus((int) $ready['id'], 'ready_for_pickup', $actor);
        self::assertNotEmpty($updated['ready_at']);
        self::assertNotEmpty($updated['queue_called_at']);
        self::assertSame(1, (int) $updated['queue_call_count']);
        $feed = (new QueueFeedService($db))->feed(30, true, true);
        self::assertCount(1, $feed['ready']);
        self::assertSame(format_queue_number((int)$ready['queue_number']), $feed['ready'][0]['number']);
        $expired = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->modify('-31 minutes')->format('Y-m-d H:i:s');
        $db->table('orders')->where('id', $ready['id'])->update(['ready_at' => $expired]);
        self::assertSame([], (new QueueFeedService($db))->feed(30, true, true)['ready']);
        $yesterday = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->modify('-1 day')->format('Y-m-d');
        $db->table('orders')->where('id', $pending['id'])->update(['queue_date' => $yesterday, 'status' => 'confirmed']);
        self::assertSame([], (new QueueFeedService($db))->feed(30, true, true)['preparing']);
    }

    public function testImageMigrationNeverOverwritesUploadedImage(): void
    {
        $db = db_connect('tests');
        $uploaded = 'uploads/products/admin-upload.webp';
        $db->table('products')->where('slug', 'crispy-fries')->update(['image' => $uploaded]);
        (new \App\Database\Migrations\AssignMenuImageUrls())->up();
        self::assertSame($uploaded, $db->table('products')->where('slug', 'crispy-fries')->get()->getRow('image'));
        $other = $db->table('products')->where('slug', 'hot-americano')->get()->getRowArray();
        self::assertSame(config(\Config\MenuImages::class)->products['hot-americano']['url'], $other['image']);
    }
}
