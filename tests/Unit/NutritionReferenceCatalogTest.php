<?php

namespace Tests\Unit;

use App\Services\NutritionReferenceCatalog;
use PHPUnit\Framework\TestCase;

final class NutritionReferenceCatalogTest extends TestCase
{
    private function sources(): string
    {
        return APPPATH . 'Database/Seeds/data/usda_reference_candidates.csv';
    }

    private function worksheet(): string
    {
        return APPPATH . 'Database/Seeds/data/recipe_measurement_workbook.csv';
    }

    public function testEveryCandidateHasARealFormatFdcIdAndAllSevenExplicitNutrientFields(): void
    {
        $check = NutritionReferenceCatalog::inspect($this->sources(), $this->worksheet());
        self::assertSame(20, $check['references']);
        self::assertSame(8, $check['products']);
    }

    public function testNoUnmeasuredDraftRecipeCanBeCountedAsCompleted(): void
    {
        $check = NutritionReferenceCatalog::inspect($this->sources(), $this->worksheet());
        self::assertSame(0, $check['measured_products']);
        self::assertCount(8, $check['missing']);
    }

    public function testUnknownFdcIdIsRejectedRatherThanTreatedAsARealSource(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'recipe-worksheet-');
        try {
            $csv = file_get_contents($this->worksheet());
            self::assertNotFalse($csv);
            $csv = str_replace(',168878,', ',999999999,', $csv);
            file_put_contents($file, $csv);
            $this->expectException(\DomainException::class);
            NutritionReferenceCatalog::inspect($this->sources(), $file);
        } finally {
            @unlink($file);
        }
    }
}
