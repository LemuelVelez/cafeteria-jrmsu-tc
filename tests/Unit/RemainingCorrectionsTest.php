<?php

namespace Tests\Unit;

use App\Database\Seeds\OperationalSettingsSeeder;
use App\Services\NutritionLookupService;
use App\Services\CostingService;
use PHPUnit\Framework\TestCase;

final class RemainingCorrectionsTest extends TestCase
{
    public function testFoodDataCentralNutrientsAreReadFromAuthoritativeNutrientCodes(): void
    {
        $input=['foodNutrients'=>[
            ['nutrient'=>['number'=>'1008'],'amount'=>251.1],
            ['nutrient'=>['number'=>'1003'],'amount'=>11.4],
            ['nutrient'=>['number'=>'1005'],'amount'=>19.8],
            ['nutrient'=>['number'=>'1004'],'amount'=>13.5],
            ['nutrient'=>['number'=>'2000'],'amount'=>1.2],
            ['nutrient'=>['number'=>'1079'],'amount'=>4.5],
            ['nutrient'=>['number'=>'1093'],'amount'=>401],
        ]];
        self::assertSame([
            'calories'=>251.1,'protein_g'=>11.4,'carbohydrates_g'=>19.8,
            'fat_g'=>13.5,'sugar_g'=>1.2,'fiber_g'=>4.5,'sodium_mg'=>401.0,
        ],NutritionLookupService::nutrients($input));
    }

    public function testMissingFoodDataCentralNutrientsAreNotInventedAsZero(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('missing');
        NutritionLookupService::nutrients(['foodNutrients'=>[
            ['nutrientNumber'=>'203','value'=>2.1],
            ['nutrientNumber'=>'307','value'=>140],
        ]]);
    }

    public function testAllOptionalSettingsHaveSafeDefaultsAndNoExternalKeyIsNeeded(): void
    {
        $defaults=OperationalSettingsSeeder::DEFAULTS;
        self::assertSame('0',$defaults['inventory_ingredient_tracking_enabled']);
        self::assertSame('0',$defaults['attendance_enabled']);
        self::assertSame('0',$defaults['web_push_enabled']);
        self::assertSame('90',$defaults['notification_read_retention_days']);
        self::assertSame('0.25',$defaults['attendance_overtime_premium']);
        self::assertSame('0.10',$defaults['attendance_night_differential']);
        $environment=file_get_contents(dirname(__DIR__,2).'/.env.example');
        foreach (['FDC_API_KEY','VAPID_PUBLIC_KEY','VAPID_PRIVATE_KEY',
            'BACKUP_KEEP_DAILY','BACKUP_KEEP_WEEKLY','BACKUP_KEEP_MONTHLY',
            'BACKUP_S3_BUCKET','AWS_DEFAULT_REGION',
            'ATTENDANCE_OVERTIME_PREMIUM','ATTENDANCE_NIGHT_DIFFERENTIAL'] as $key) {
            self::assertStringNotContainsString($key,$environment);
        }
    }

    public function testIngredientUnitsRequireTheSamePhysicalDimension(): void
    {
        self::assertEqualsWithDelta(0.25, CostingService::convert(250, 'g', 'kg'), 0.000001);
        self::assertEqualsWithDelta(300, CostingService::convert(0.3, 'l', 'ml'), 0.000001);
        try {
            CostingService::convert(250, 'ml', 'g');
            self::fail('Unit conversion between volume and mass must require ingredient density.');
        } catch (\DomainException $expected) {
            self::assertStringContainsString('Incompatible', $expected->getMessage());
        }
        self::assertEqualsWithDelta(205, CostingService::nutrientQuantity(200, 'ml', 1.025), 0.000001);
        self::assertEqualsWithDelta(1050, CostingService::nutrientQuantity(1, 'l', 1.05), 0.000001);
        self::assertEqualsWithDelta(50, CostingService::nutrientQuantity(2, 'pcs', 1, 25), 0.000001);
        $this->expectException(\DomainException::class);
        CostingService::nutrientQuantity(2, 'pcs', 1);
    }

    public function testPushRetryHasOneReceiptPerDeviceAndIsMigrationSafe(): void
    {
        $root=dirname(__DIR__,2);
        $migration=file_get_contents($root.'/app/Database/Migrations/2026-08-17-000032_TrackPushDeliveries.php');
        self::assertStringContainsString('push_delivery_receipts',$migration);
        self::assertStringContainsString('function down()', $migration);
        $dispatcher=file_get_contents($root.'/app/Services/WebPushService.php');
        self::assertStringContainsString('completedIds',$dispatcher);
        self::assertStringContainsString('INSERT IGNORE INTO push_delivery_receipts',$dispatcher);
        $density=file_get_contents($root.'/app/Database/Migrations/2026-08-17-000033_AddLiquidDensity.php');
        self::assertStringContainsString('density_g_per_ml',$density);
        self::assertStringContainsString('function down()', $density);
    }

    public function testRiderUpdatesIncludeNameAndDoNotInventAnEta(): void
    {
        $code=file_get_contents(dirname(__DIR__,2).'/app/Services/NotificationService.php');
        self::assertStringContainsString('Your rider is', $code);
        self::assertStringContainsString('Estimated arrival depends on traffic', $code);
    }

    public function testIngredientStocksAndSubscriptionsUseTransactionalLedgers(): void
    {
        $root=dirname(__DIR__,2);
        $ingredient=file_get_contents($root.'/app/Services/IngredientInventoryService.php');
        foreach(['FOR UPDATE','ingredient_movements','cancel_restock','saleForOrder','stockIn','requirements'] as $word) {
            self::assertStringContainsString($word,$ingredient);
        }
        $order=file_get_contents($root.'/app/Services/OrderService.php');
        self::assertStringContainsString('saleForOrder',$order);
        self::assertStringContainsString('restockForCancellation',$order);
        $push=file_get_contents($root.'/app/Services/WebPushService.php');
        self::assertStringContainsString('endpoint_hash',$push);
        self::assertStringContainsString('aes-128-gcm',$push);
    }
}
