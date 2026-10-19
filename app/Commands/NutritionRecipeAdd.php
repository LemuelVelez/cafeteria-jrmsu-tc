<?php

namespace App\Commands;

use App\Services\NutritionCatalogImport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Adds only quantities explicitly measured/provided by the team. */
final class NutritionRecipeAdd extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'nutrition:recipe-add';
    protected $description = 'Add a measured line to recipes.csv without changing seeded database records.';
    protected $usage = 'nutrition:recipe-add <PRODUCT_SLUG> <INGREDIENT_NAME> <QUANTITY> <UNIT> <WASTAGE_PERCENT> <SERVINGS> <COOKING_YIELD> <PACKAGING_COST>';

    public function run(array $params): int
    {
        if (count($params) !== 8) {
            CLI::error('Usage: ' . $this->usage);
            return EXIT_ERROR;
        }
        try {
            $columns = ['product_slug', 'ingredient_name', 'quantity', 'unit',
                'wastage_percent', 'recipe_servings', 'cooking_yield', 'packaging_cost'];
            NutritionCatalogImport::addRecipe(
                APPPATH . 'Database/Seeds/data/ingredients.csv',
                APPPATH . 'Database/Seeds/data/recipes.csv',
                array_combine($columns, array_map('strval', $params)),
            );
            CLI::write('Measured recipe line added. Run php spark nutrition:catalog-audit to inspect remaining gaps.', 'green');
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Recipe input rejected: ' . $error->getMessage());
            return EXIT_ERROR;
        }
    }
}
