<?php

namespace App\Commands;

use App\Services\NutritionCatalogImport;
use App\Services\NutritionLookupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Fetches and imports only actual, complete USDA nutrient records. */
final class NutritionIngredientImport extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'nutrition:ingredient-import';
    protected $description = 'Import sourced USDA nutrient data into ingredients.csv using operator-supplied measurements and costs.';
    protected $usage = 'nutrition:ingredient-import <FDC_ID> <NAME> <UNIT> <COST> <DENSITY_OR_DASH> <GRAMS_PER_PIECE_OR_DASH> <REORDER_LEVEL> <OPENING_STOCK>';

    public function run(array $params): int
    {
        if (count($params) !== 8 || ! ctype_digit((string) $params[0]) || (int) $params[0] < 1) {
            CLI::error('Usage: ' . $this->usage);
            return EXIT_ERROR;
        }
        try {
            $food = (new NutritionLookupService())->details((int) $params[0]);
            NutritionCatalogImport::addIngredient(APPPATH . 'Database/Seeds/data/ingredients.csv', $food, [
                'name' => (string) $params[1],
                'purchase_unit' => (string) $params[2],
                'cost_per_unit' => (string) $params[3],
                'density_g_per_ml' => $params[4] === '-' ? '' : (string) $params[4],
                'grams_per_piece' => $params[5] === '-' ? '' : (string) $params[5],
                'reorder_level' => (string) $params[6],
                'opening_stock' => (string) $params[7],
            ]);
            CLI::write('Imported USDA FDC #' . (int) $params[0] . ' into ingredients.csv.', 'green');
            CLI::write('Confirm the chosen food matches the recipe ingredient; demo prices require receipts.', 'yellow');
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Ingredient import blocked: ' . $error->getMessage());
            return EXIT_ERROR;
        }
    }
}
