<?php

namespace Tests\Unit;

use App\Services\MediaStorageService;
use App\Services\QueueFeedService;
use PHPUnit\Framework\TestCase;

final class QueueAndMenuImageTest extends TestCase
{
    public function testFormatQueueNumberAndFallback(): void
    {
        self::assertSame('042', format_queue_number(42));
        self::assertSame('001', format_queue_number(1));
        self::assertSame('ORDER-LEGACY', format_queue_number(null, 'ORDER-LEGACY'));
        // A prefix comes from the saved setting, not a hard-coded letter in the helper.
        $helper = file_get_contents(dirname(__DIR__, 2) . '/app/Helpers/cafeteria_helper.php');
        self::assertStringContainsString("getValue('queue_number_prefix'", $helper);
    }

    public function testPublicQueuePresenterHasAnExplicitPrivacyProjection(): void
    {
        $now = new \DateTimeImmutable('2026-10-20 12:00:00', new \DateTimeZone('Asia/Manila'));
        $row = ['queue_date' => '2026-10-20', 'order_type' => 'pickup',
            'queue_number' => 42, 'status' => 'ready_for_pickup',
            'ready_at' => '2026-10-20 11:50:00', 'queue_called_at' => '2026-10-20 11:52:00',
            'queue_call_count' => 2, 'customer_id' => 451, 'customer_name' => 'Private user',
            'total' => '123.45', 'order_number' => 'SECRET', 'notes' => 'Internal',
            'delivery_address' => 'Private street'];
        $feed = QueueFeedService::present([$row], true, true, $now);
        self::assertSame(['number' => '042', 'called_at' => '2026-10-20T11:52:00+08:00', 'calls' => 2], $feed['ready'][0]);
        foreach (['customer_id','customer_name','name','total','order_number','notes','delivery_address','address'] as $private) {
            self::assertStringNotContainsString('"' . $private . '"', json_encode($feed));
        }
        // A supplied prefix must be reusable for all orders without another DB lookup.
        $custom = QueueFeedService::present([$row, array_replace($row, ['queue_number' => 43])], true, false, $now, 30, 'P-');
        self::assertSame(['P-042', 'P-043'], array_column($custom['ready'], 'number'));
    }

    public function testEverySeededProductHasAnAllowlistedHttpsImage(): void
    {
        $catalog = config(\Config\MenuImages::class);
        foreach (['chicken-adobo-rice','pork-sisig-rice','fried-chicken-meal','cheese-burger',
            'crispy-fries','iced-spanish-latte','hot-americano','calamansi-juice'] as $slug) {
            self::assertArrayHasKey($slug, $catalog->products);
            $url = $catalog->products[$slug]['url'];
            self::assertSame('https', parse_url($url, PHP_URL_SCHEME));
            self::assertContains(parse_url($url, PHP_URL_HOST), ['images.unsplash.com','images.pexels.com','cdn.pixabay.com']);
        }
        self::assertCount(8, $catalog->products);
        self::assertCount(4, $catalog->categories);
    }

    public function testStoredImageWinsOverCatalogAndRemoteMediaCanBeRemovedSafely(): void
    {
        $media = new MediaStorageService();
        $stored = 'uploads/products/2026/10/custom.webp';
        $product = ['slug' => 'chicken-adobo-rice', 'category_name' => 'Rice Meals',
            'image' => $stored, 'name' => 'Chicken Adobo Rice'];
        $image = product_image($product);
        self::assertSame(media_url($stored), $image['url']);
        self::assertSame(config(\Config\MenuImages::class)->categories['Rice Meals']['url'], $image['fallback']);
        $remote = config(\Config\MenuImages::class)->products['chicken-adobo-rice']['url'];
        self::assertSame($remote, $media->url($remote));
        $media->delete($remote); // No unlink or network request for externally hosted photos.
        self::assertSame($remote, product_image(array_replace($product, ['image' => null]))['url']);
    }
}
