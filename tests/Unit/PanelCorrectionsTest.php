<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\OrderService;
use App\Validation\PasswordRules;
use PHPUnit\Framework\TestCase;

final class PanelCorrectionsTest extends TestCase
{
    public function testStrongPasswordPolicyRejectsWeakCommonAndPersonalPasswords(): void
    {
        $rule = new PasswordRules();
        $error = null;
        self::assertFalse($rule->strong_password('weakpass', null, ['name'=>'Maria Santos','email'=>'maria@example.com'], $error));
        self::assertFalse($rule->strong_password('Password123!', null, ['name'=>'Other User','email'=>'other@example.com'], $error));
        self::assertFalse($rule->strong_password('Maria#2026Secure', null, ['name'=>'Maria','email'=>'maria@example.com'], $error));
        self::assertTrue($rule->strong_password('River!Quartz27', null, ['name'=>'Maria','email'=>'maria@example.com'], $error));
    }

    public function testOrderStatusWorkflowUsesPanelLabels(): void
    {
        self::assertSame('Ready for Pickup', OrderStatus::ReadyForPickup->label());
        self::assertSame('Completed', OrderStatus::Completed->label());
        self::assertSame(['confirmed','cancelled'], OrderStatus::Pending->transitions());
        self::assertContains('ready_for_pickup', OrderStatus::Preparing->transitions());
        self::assertTrue(OrderStatus::Completed->isTerminal());
    }

    public function testRoleTransitionsProtectPickupAndAssignedDelivery(): void
    {
        $pickup = ['status'=>'ready_for_pickup','order_type'=>'pickup','rider_id'=>null];
        self::assertContains('completed', OrderService::allowedTransitions($pickup, ['role'=>'cashier','id'=>2]));
        self::assertNotContains('out_for_delivery', OrderService::allowedTransitions($pickup, ['role'=>'cashier','id'=>2]));

        $delivery = ['status'=>'ready_for_pickup','order_type'=>'delivery','rider_id'=>7];
        self::assertSame(['out_for_delivery'], OrderService::allowedTransitions($delivery, ['role'=>'rider','id'=>7]));
        self::assertSame([], OrderService::allowedTransitions($delivery, ['role'=>'rider','id'=>8]));
    }

    public function testSecurityOwnershipInventoryBarcodeQrNotificationAndRevenueContractsExist(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = file_get_contents($root.'/app/Services/AuthService.php');
        $orders = file_get_contents($root.'/app/Controllers/Api/OrderApiController.php');
        $routes = file_get_contents($root.'/app/Config/Routes.php');
        $inventory = file_get_contents($root.'/app/Services/InventoryService.php');
        $productController = file_get_contents($root.'/app/Controllers/Admin/ProductController.php');
        $qr = file_get_contents($root.'/app/Services/OrderQrService.php');
        $notifications = file_get_contents($root.'/app/Services/NotificationService.php');
        $reports = file_get_contents($root.'/app/Services/ReportService.php');
        $migration = file_get_contents($root.'/app/Database/Migrations/2026-08-17-000017_NormalizeOrderStatuses.php');

        self::assertStringContainsString('failed_login_attempts', $auth);
        self::assertStringContainsString('locked_until', $auth);
        self::assertStringContainsString("\$user['role'] === 'customer'", $orders);
        self::assertStringContainsString("\$user['role'] === 'rider'", $orders);
        self::assertStringContainsString("products/barcode/(:segment)", $routes);
        self::assertStringContainsString('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE', $inventory);
        self::assertStringContainsString("if (\$id === null)", $productController);
        self::assertStringContainsString("hash_hmac('sha256'", $qr);
        self::assertStringContainsString('statusChanged', $notifications);
        self::assertStringContainsString("payment_status = 'paid'", $reports);
        self::assertStringContainsString("ready_for_pickup", $migration);
        self::assertStringContainsString("completed", $migration);
    }
}
