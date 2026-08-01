<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProjectStructureTest extends TestCase
{
    public function testRequiredProjectFilesExist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'app/Config/Routes.php',
            'app/Services/OrderService.php',
            'app/Enums/PaymentMethod.php',
            'frontend/dev-server.mjs',
            'cafe',
            'package.json',
            'app/Views/layouts/main.php',
            'public/assets/css/app.css',
            'public/assets/img/jrmsu-cafeteria-logo.png',
            '.env.example',
        ];

        foreach ($required as $file) {
            self::assertFileExists($root . DIRECTORY_SEPARATOR . $file);
        }
    }

    public function testRiderDeliveryUsesCopyAddressInsteadOfExternalMap(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Views/rider/deliveries/show.php');
        $script = file_get_contents($root . '/public/assets/js/app.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringNotContainsString('google.com/maps', $view);
        self::assertStringNotContainsString('Open in Maps', $view);
        self::assertStringContainsString('data-copy-target="#deliveryAddress"', $view);
        self::assertStringContainsString('enhanceCopyButtons', $script);
        self::assertStringContainsString('navigator.clipboard', $script);
    }

    public function testOrderStatusBadgeUsesCompactAccessibleStyles(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/app/Helpers/cafeteria_helper.php');
        $styles = file_get_contents($root . '/public/assets/css/app.css');

        self::assertIsString($helper);
        self::assertIsString($styles);
        self::assertStringContainsString('order-status-badge order-status-badge--', $helper);
        self::assertStringContainsString('aria-hidden="true"', $helper);
        self::assertStringNotContainsString('badge rounded-pill bg-', $helper);
        self::assertStringContainsString('.order-status-badge--confirmed', $styles);
        self::assertMatchesRegularExpression('/\.order-status-badge\s*\{[^}]*align-self:\s*flex-start;/s', $styles);
        self::assertMatchesRegularExpression('/\.order-status-badge\s*\{[^}]*white-space:\s*nowrap;/s', $styles);
    }

    public function testEnvironmentTemplateDoesNotContainGeneratedApplicationKey(): void
    {
        $env = file_get_contents(dirname(__DIR__, 2) . '/.env.example');
        self::assertIsString($env);
        self::assertStringContainsString('replace-with-a-long-random-secret-key', $env);
    }

    public function testRoleWorkspacesExposeTheirCoreFeatureRoutes(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Config/Routes.php');
        self::assertIsString($routes);

        $requiredRoutes = [
            // Administrator workspace.
            "get('dashboard', 'Admin\\\\DashboardController::index')",
            "get('products', 'Admin\\\\ProductController::index')",
            "get('categories', 'Admin\\\\CategoryController::index')",
            "get('orders', 'Admin\\\\OrderController::index')",
            "get('users', 'Admin\\\\UserController::index')",
            "get('customers', 'Admin\\\\CustomerController::index')",
            "get('riders', 'Admin\\\\RiderController::index')",
            "get('promos', 'Admin\\\\PromoController::index')",
            "get('reports', 'Admin\\\\ReportController::index')",
            "get('settings', 'Admin\\\\SettingController::index')",
            // Cashier workspace.
            "get('dashboard', 'Cashier\\\\DashboardController::index')",
            "get('pos', 'Cashier\\\\PosController::index')",
            "get('orders', 'Cashier\\\\OrderController::index')",
            // Rider workspace.
            "get('dashboard', 'Rider\\\\DashboardController::index')",
            "get('deliveries', 'Rider\\\\DeliveryController::index')",
            "post('deliveries/(:num)/status', 'Rider\\\\DeliveryController::status/$1')",
            // Customer workspace.
            "get('dashboard', 'Customer\\\\DashboardController::index')",
            "get('menu', 'Customer\\\\MenuController::index')",
            "get('cart', 'Customer\\\\CartController::index')",
            "get('checkout', 'Customer\\\\CheckoutController::index')",
            "get('orders', 'Customer\\\\OrderController::index')",
            "get('reviews', 'Customer\\\\ReviewController::index')",
        ];

        foreach ($requiredRoutes as $route) {
            self::assertStringContainsString('$routes->' . $route . ';', $routes, $route);
        }
    }

    public function testOrderCheckoutAndDeliveryWorkflowSafeguardsRemainInPlace(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/OrderService.php');
        self::assertIsString($service);

        self::assertStringContainsString('INNER JOIN categories ON categories.id = products.category_id', $service);
        self::assertStringContainsString('AND categories.deleted_at IS NULL', $service);
        self::assertStringContainsString('AND categories.is_active = 1', $service);
        self::assertStringContainsString('max(0.0, (float) $this->settings->getValue', $service);
        self::assertStringContainsString("if (\$status === 'delivered' && \$isDelivery", $service);
        self::assertStringContainsString("return ['out_for_delivery'];", $service);
        self::assertStringContainsString("return ['delivered'];", $service);
    }

    public function testCustomerEmailChangesRequireFreshVerification(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Account/SettingController.php');
        self::assertIsString($controller);

        self::assertStringContainsString('$requiresEmailVerification = $emailChanged', $controller);
        self::assertStringContainsString("\$data['requires_email_verification'] = 1", $controller);
        self::assertStringContainsString("\$data['email_verified_at'] = null", $controller);
        self::assertStringContainsString("\$this->session->remove('user')", $controller);
        self::assertStringContainsString("redirect()->to('/email-verification')", $controller);
    }

    public function testAdminDataValidationContractsRemainDefensive(): void
    {
        $root = dirname(__DIR__, 2);
        $promoController = file_get_contents($root . '/app/Controllers/Admin/PromoController.php');
        $promoModel = file_get_contents($root . '/app/Models/PromoModel.php');
        $promoService = file_get_contents($root . '/app/Services/PromoService.php');
        $productController = file_get_contents($root . '/app/Controllers/Admin/ProductController.php');
        $productApi = file_get_contents($root . '/app/Controllers/Api/ProductApiController.php');
        $reportController = file_get_contents($root . '/app/Controllers/Admin/ReportController.php');

        foreach ([$promoController, $promoModel, $promoService, $productController, $productApi, $reportController] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("\$discountType === 'percentage' && \$discountValue > 100", $promoController);
        self::assertStringContainsString("DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i'", $promoController);
        self::assertStringContainsString("'minimum_order' => 'required|decimal|greater_than_equal_to[0]'", $promoModel);
        self::assertStringContainsString('$subtotal < 0', $promoService);
        self::assertStringContainsString("where(['id' => \$categoryId, 'is_active' => 1])", $productController);
        self::assertStringContainsString("where(['id' => \$categoryId, 'is_active' => 1])", $productApi);
        self::assertStringContainsString('$from > $to', $reportController);
        self::assertStringContainsString("DateTimeImmutable::createFromFormat('!Y-m-d'", $reportController);
    }

    public function testCustomerAndCashierScreensNeverDisplayNegativeDeliveryFees(): void
    {
        $root = dirname(__DIR__, 2);
        $customer = file_get_contents($root . '/app/Controllers/Customer/CheckoutController.php');
        $cashier = file_get_contents($root . '/app/Controllers/Cashier/PosController.php');

        self::assertIsString($customer);
        self::assertIsString($cashier);
        self::assertStringContainsString("'deliveryFee' => max(0.0", $customer);
        self::assertStringContainsString("'deliveryFee' => max(0.0", $cashier);
    }

    public function testFeaturedProductsUseTheirStoredImagesWhenAvailable(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/customer/dashboard.php');
        self::assertIsString($view);
        self::assertStringContainsString("media_url(\$product['image'])", $view);
    }

}
