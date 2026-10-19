<?php

namespace App\Commands;

use App\Services\NutritionReferenceCatalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Review the non-seeding USDA lookup file and real-recipe measurement gaps. */
final class NutritionReferences extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'nutrition:references';
    protected $description = 'Audit documented USDA nutrient candidates and the eight-product measurement worksheet.';
    protected $usage = 'nutrition:references';

    public function run(array $params): int
    {
        try {
            $folder = APPPATH . 'Database/Seeds/data/';
            $result = NutritionReferenceCatalog::inspect(
                $folder . 'usda_reference_candidates.csv',
                $folder . 'recipe_measurement_workbook.csv'
            );
            CLI::write('USDA reference candidates: ' . $result['references']);
            CLI::write('Restaurant recipes with fully entered measurements and sign-off: '
                . $result['measured_products'] . '/' . $result['products']);
            foreach ($result['missing'] as $slug) {
                CLI::write('  Needs actual restaurant measurements: ' . $slug, 'yellow');
            }
            CLI::write('Reference candidates are NOT seeded nutrition or approved kitchen recipes.', 'yellow');
            return $result['missing'] === [] ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (\Throwable $e) {
            CLI::error('Nutrition reference catalog invalid: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }
}
