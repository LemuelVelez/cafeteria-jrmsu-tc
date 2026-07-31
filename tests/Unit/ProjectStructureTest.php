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

    public function testEnvironmentTemplateDoesNotContainGeneratedApplicationKey(): void
    {
        $env = file_get_contents(dirname(__DIR__, 2) . '/.env.example');
        self::assertIsString($env);
        self::assertStringContainsString('replace-with-a-long-random-secret-key', $env);
    }
}
