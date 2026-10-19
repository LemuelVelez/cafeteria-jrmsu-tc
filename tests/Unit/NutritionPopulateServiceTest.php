<?php

namespace Tests\Unit;

use App\Services\NutritionPopulateService;
use App\Services\NutritionReferenceCatalog;
use App\Services\SourcedRecipeCatalog;
use PHPUnit\Framework\TestCase;

/** All quantities and USDA nutrient values below are synthetic test fixtures. */
final class NutritionPopulateServiceTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/cafeteria-populate-' . bin2hex(random_bytes(6));
        mkdir($this->folder);
        $source = APPPATH . 'Database/Seeds/data/';
        foreach (['ingredients.csv', 'recipes.csv', 'recipe_measurement_workbook.csv', 'usda_reference_candidates.csv'] as $name) {
            copy($source . $name, $this->folder . '/' . $name);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') ?: [] as $file) unlink($file);
        if (is_file($this->folder . '/.populate.lock')) unlink($this->folder . '/.populate.lock');
        rmdir($this->folder);
        parent::tearDown();
    }

    public function testDecliningProductNeverChangesAnyCsv(): void
    {
        $before = $this->fingerprints();
        self::assertSame('skipped', NutritionPopulateService::populate($this->folder,
            'hot-americano', static fn(string $prompt): string => 'NO',
            static function (int $id): array { throw new \LogicException('USDA should not be contacted'); }));
        self::assertSame($before, $this->fingerprints());
    }

    public function testOneSignedProductPopulatesExactlyThreeCsvsAndDoesNotDuplicate(): void
    {
        $answers = [
            'YES', '1', '1', '0', 'Kitchen Test Approver', '2026-10-19',
            'YES', 'YES', 'Brewed coffee', '100', 'g', '0',
            'g', '1.50', '0', '0', '', '',
            'NO', 'YES', 'YES',
        ];
        $ask = static function (string $prompt) use (&$answers): string {
            if ($answers === []) throw new \LogicException('Unexpected prompt: ' . $prompt);
            return array_shift($answers);
        };
        self::assertSame('saved', NutritionPopulateService::populate($this->folder,
            'hot-americano', $ask, self::food(...)));
        self::assertSame([], $answers);
        $ingredients = SourcedRecipeCatalog::read($this->folder . '/ingredients.csv', SourcedRecipeCatalog::INGREDIENT_COLUMNS);
        $recipes = SourcedRecipeCatalog::read($this->folder . '/recipes.csv', SourcedRecipeCatalog::RECIPE_COLUMNS);
        $worksheet = SourcedRecipeCatalog::read($this->folder . '/recipe_measurement_workbook.csv', NutritionReferenceCatalog::WORKSHEET_FIELDS);
        self::assertCount(1, $ingredients);
        self::assertCount(1, $recipes);
        self::assertCount(1, array_filter($worksheet, static fn(array $w): bool => $w['product_slug'] === 'hot-americano'));
        self::assertSame('USDA FDC #171890 (SR Legacy)', $ingredients[0]['source_reference']);
        self::assertSame('Kitchen Test Approver', end($worksheet)['kitchen_approved_by']);
        self::assertSame('100', $recipes[0]['quantity']);
        $after = $this->fingerprints();
        self::assertSame('already_complete', NutritionPopulateService::populate($this->folder,
            'hot-americano', static function (): string { throw new \LogicException('Should not prompt again'); }, self::food(...)));
        self::assertSame($after, $this->fingerprints());
    }

    public function testIncompleteOrInvalidMeasurementsNeverWriteAnyFile(): void
    {
        $before = $this->fingerprints();
        $answers = [
            'YES', '1', '1', '0', 'Kitchen Test Approver', '2026-10-19',
            'YES', 'YES', 'Brewed coffee', '0', 'g', '0',
            'g', '1.50', '0', '0', '', '',
            'NO', 'YES', 'YES',
        ];
        try {
            NutritionPopulateService::populate($this->folder, 'hot-americano',
                static function (string $p) use (&$answers): string { return array_shift($answers) ?? ''; }, self::food(...));
            self::fail('Zero recipe quantity must fail.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('quantity', $e->getMessage());
        }
        self::assertSame($before, $this->fingerprints());
    }

    public function testWrongSourceIdRejectsBeforeAnyDataWrite(): void
    {
        $before = $this->fingerprints();
        $answers = ['YES', '1', '1', '0', 'Kitchen Test Approver', '2026-10-19', 'YES'];
        $this->expectException(\DomainException::class);
        try {
            NutritionPopulateService::populate($this->folder, 'hot-americano',
                static function (string $p) use (&$answers): string { return array_shift($answers) ?? ''; },
                static fn(int $id): array => ['fdcId' => 9, 'dataType' => 'SR Legacy']);
        } finally {
            self::assertSame($before, $this->fingerprints());
        }
    }

    private function fingerprints(): array
    {
        $hashes = [];
        foreach (['ingredients.csv', 'recipes.csv', 'recipe_measurement_workbook.csv'] as $name) {
            $hashes[$name] = hash_file('sha256', $this->folder . '/' . $name);
        }
        return $hashes;
    }

    private static function food(int $id): array
    {
        return [
            'fdcId' => $id, 'dataType' => 'SR Legacy', 'description' => 'Synthetic test brewed coffee',
            'foodNutrients' => array_map(
                static fn(int $id, float $value): array => ['nutrient' => ['number' => (string) $id], 'amount' => $value],
                [208, 203, 205, 204, 269, 291, 307],
                [1.0, .12, 0.0, .02, 0.0, 0.0, 2.0],
            ),
        ];
    }
}
