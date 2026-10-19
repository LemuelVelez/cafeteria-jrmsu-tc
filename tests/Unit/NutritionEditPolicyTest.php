<?php

namespace Tests\Unit;

use App\Services\NutritionEditPolicy;
use PHPUnit\Framework\TestCase;

/** Policies use synthetic figures, never asserted as sourced food data. */
final class NutritionEditPolicyTest extends TestCase
{
    private function verified(): array
    {
        return [
            'name' => 'Synthetic test dish', 'price' => '95.00',
            'serving_size' => 'one meal', 'calories' => 280,
            'protein_g' => '12.50', 'carbohydrates_g' => '34.20',
            'fat_g' => '10.00', 'sugar_g' => '1.00',
            'fiber_g' => '2.00', 'sodium_mg' => 230,
            'nutrition_status' => 'verified', 'nutrition_source' => 'recipe_sourced',
            'nutrition_verified_by' => 7, 'nutrition_verified_at' => '2026-10-18 12:00:00',
        ];
    }

    public function testMetadataAndEquivalentDecimalFormattingDoNotRevokeSignOff(): void
    {
        $before = $this->verified();
        $changes = ['name' => 'Another name', 'price' => '90',
            'calories' => '280', 'protein_g' => '12.5', 'fat_g' => '10.0'];
        self::assertSame($changes, NutritionEditPolicy::apply($before, $changes, true));
    }

    public function testDirectManualNutritionEditRevokesOldSignOffAndSource(): void
    {
        $changes = NutritionEditPolicy::apply($this->verified(),
            ['calories' => '290', 'name' => 'Same product'], false);
        self::assertSame('estimated', $changes['nutrition_status']);
        self::assertSame('manual', $changes['nutrition_source']);
        self::assertNull($changes['nutrition_verified_by']);
        self::assertNull($changes['nutrition_verified_at']);
        self::assertSame('290', $changes['calories']);
    }

    public function testExplicitRemovalOfNutrientAlsoRevokesOldSignOff(): void
    {
        $changes = NutritionEditPolicy::apply($this->verified(), ['calories' => null], false);
        self::assertSame('estimated', $changes['nutrition_status']);
    }

    public function testRecipeDerivedNutrientsCannotBeOverwrittenFromProductFormsOrApi(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Food Costing');
        NutritionEditPolicy::apply($this->verified(), ['protein_g' => '11.5'], true);
    }

    public function testCreatingProductRemainsEstimatedByDatabaseDefaults(): void
    {
        $data = ['calories' => null, 'name' => 'Synthetic new product'];
        self::assertSame($data, NutritionEditPolicy::apply(null, $data, false));
    }

    public function testUnchangedSubsetFromApiDoesNotOverwriteVerification(): void
    {
        $values = ['price' => '120.00'];
        self::assertSame($values, NutritionEditPolicy::apply($this->verified(), $values, false));
    }
}
