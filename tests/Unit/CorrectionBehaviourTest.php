<?php

namespace Tests\Unit;

use App\Database\Migrations\CreateStructuredAllergens;
use App\Controllers\Admin\ReportController;
use App\Services\BarcodeService;
use App\Services\CostingService;
use App\Services\DatabaseBackupService;
use PHPUnit\Framework\TestCase;

final class CorrectionBehaviourTest extends TestCase
{
    public function testAllDemoBarcodesPassAndLegacyValuesFail(): void
    {
        $valid = ['2000000000015','2000000000022','2000000000039','2000000000046',
            '2000000000053','2000000000060','2000000000077','2000000000084'];
        foreach ($valid as $value) self::assertTrue(BarcodeService::valid($value),$value);
        for ($i=101;$i<=108;$i++) self::assertFalse(BarcodeService::valid('480000000'.$i));
    }

    public function testAllergenNormalizerIsExactNotSubstringBased(): void
    {
        self::assertSame('egg',CreateStructuredAllergens::normalize('eggs'));
        self::assertSame('wheat_gluten',CreateStructuredAllergens::normalize('gluten'));
        self::assertNull(CreateStructuredAllergens::normalize('eggplant'));
        self::assertSame('crustacean_shellfish',CreateStructuredAllergens::normalize('shrimp'));
    }

    public function testCookingLossDoesNotRemoveNutrients(): void
    {
        self::assertSame(['calories'=>120.0,'protein_g'=>9.0],
            CostingService::perServingNutrients(['calories'=>240,'protein_g'=>18],2));
        self::assertEqualsWithDelta(50.0, CostingService::nutrientQuantity(2,'pcs',1,25),0.001);
        self::assertEqualsWithDelta(0.1,CostingService::ingredientUnits(2,'pcs','kg',1,50),0.00001);
        $this->expectException(\DomainException::class);
        CostingService::nutrientQuantity(2,'pcs',1);
    }

    public function testCsvFormulaCellsAreEscaped(): void
    {
        foreach (['=SUM(1,2)','+cmd','-1','@MAIL',"\tfoo","\rbar"] as $value) {
            self::assertSame("'".$value, ReportController::safeCsvCell($value));
        }
        self::assertSame('Normal row',ReportController::safeCsvCell('Normal row'));
    }

    public function testMySqlCredentialsEscapedWithoutJsonSlashEncoding(): void
    {
        $db=(object)['username'=>'test','password'=>'a/"b#c', 'hostname'=>'localhost','port'=>3336];
        $result=DatabaseBackupService::clientOptions($db);
        self::assertStringContainsString('port=3336',$result);
        self::assertStringContainsString('a/\\"b#c',$result);
        self::assertStringNotContainsString('\\/',$result);
    }
}
