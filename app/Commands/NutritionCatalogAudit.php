<?php

namespace App\Commands;

use App\Database\Seeds\IngredientSeeder;
use App\Database\Seeds\RecipeSeeder;
use App\Services\NutritionCatalogAudit as Catalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Reads the source CSVs only; does not connect to MySQL or mutate data. */
final class NutritionCatalogAudit extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'nutrition:catalog-audit';
    protected $description = 'Check all eight demo recipe CSVs for sourced ingredients and consistent measurable quantities.';
    protected $usage = 'nutrition:catalog-audit';

    public function run(array $params): int
    {
        try {
            $result = Catalog::assess(IngredientSeeder::rows(), RecipeSeeder::rows());
            CLI::write('Sourced demo recipes: ' . $result['covered'] . '/' . $result['required']);
            foreach ($result['errors'] as $error) CLI::write('  BLOCKED: ' . $error, 'yellow');
            if (! $result['ready']) {
                CLI::error('The nutrition catalog is incomplete. Source IDs also require human validation.');
                return EXIT_ERROR;
            }
            CLI::write('CSV coverage is complete. Human source and nutrition verification are still required.', 'green');
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Nutrition catalog invalid: ' . $error->getMessage());
            return EXIT_ERROR;
        }
    }
}
