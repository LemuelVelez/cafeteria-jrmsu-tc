<?php

namespace App\Commands;

use App\Database\Seeds\DatabaseSeeder;
use App\Services\DemoProductNutritionCatalog;
use App\Services\NutritionLookupService;
use App\Services\NutritionPopulateService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** One-command demo setup; signed nutrition measurements are always optional. */
class Populate extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'populate';
    protected $description = 'Seed all available cafeteria demo data without questions; use guided mode for real recipes.';
    protected $usage = 'populate [guided [product-slug]|product-slug]';

    public function run(array $params): int
    {
        // No arguments means: run the existing migration-aware, registry-based
        // seeding command. This does NOT fabricate kitchen measurements or write
        // unapproved recipe CSV rows.
        if ($params === []) {
            return $this->seedDemo();
        }

        // Keep the older per-product invocation working. The operator must
        // explicitly opt into interactive kitchen-data entry.
        if ($params[0] === 'guided') {
            array_shift($params);
        }
        if (count($params) > 1 || ($params !== []
            && ! in_array($params[0], DemoProductNutritionCatalog::SEEDERS, true))) {
            CLI::error('Usage: php spark ' . $this->usage);
            return EXIT_ERROR;
        }

        return $this->guided($params);
    }

    /** Runs the normal seed command with its migration and registry checks. */
    protected function seedDemo(): int
    {
        CLI::write('Populating all available demo data (no questions)...', 'cyan');
        try {
            $result = $this->runSeedCommand();
            if (is_int($result) && $result !== EXIT_SUCCESS) {
                CLI::error('Demo seeding failed. See the seed command error above.');
                return $result;
            }

            if (! $this->coreSeedIsReady()) {
                CLI::error('Demo seeding is incomplete. Check the registered core seeders.');
                return EXIT_ERROR;
            }

            $ready = $this->readyNutritionCount();

            CLI::write('Demo menu and opening stock: 8/8 products ready.', 'green');
            if ($ready < count(DemoProductNutritionCatalog::SEEDERS)) {
                CLI::write('Nutrition recipes: ' . $ready . '/8 approved source catalogs. Missing recipes remain DEFERRED, not fabricated.', 'yellow');
                CLI::write('No measurements or kitchen approvals were invented or edited.', 'yellow');
            } else {
                CLI::write('Approved source catalogs: 8/8. See seed output for database verification.', 'green');
            }
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Demo population failed: ' . $error->getMessage());
            return EXIT_ERROR;
        }
    }

    /** Run the registered CLI seed command; overridable for unit tests. */
    protected function runSeedCommand(): mixed
    {
        return $this->call('seed');
    }

    /** Verify the actual demo data, not merely a zero exit code. */
    protected function coreSeedIsReady(): bool
    {
        $db = db_connect();
        foreach (DatabaseSeeder::SEEDERS as $seeder) {
            if (in_array($seeder, ['IngredientSeeder', 'RecipeSeeder'], true)
                || DemoProductNutritionCatalog::slugForSeeder($seeder) !== null) {
                continue; // Unapproved nutritional measurements are not demo data.
            }
            if (! DatabaseSeeder::isSatisfied($seeder, $db)) {
                return false;
            }
        }
        return true;
    }

    protected function readyNutritionCount(): int
    {
        $ready = 0;
        foreach (DemoProductNutritionCatalog::SEEDERS as $slug) {
            if (DemoProductNutritionCatalog::status($slug)['ready']) {
                $ready++;
            }
        }
        return $ready;
    }

    /** An explicit, optional workflow for authentic measured recipe data. */
    protected function guided(array $params): int
    {
        if (! defined('STDIN') || ! self::hasInteractiveInput()) {
            CLI::error('Guided entry requires a terminal. Run php spark populate for demo seeding without prompts.');
            return EXIT_ERROR;
        }
        CLI::write('Guided recipe entry requires actual kitchen measurements and approval.', 'yellow');
        CLI::write('Three CSVs are saved only for a complete, sourced recipe.', 'yellow');
        $lookup = new NutritionLookupService();
        $ask = static function (string $question): string {
            CLI::write($question, 'cyan');
            $answer = fgets(STDIN);
            if ($answer === false) {
                throw new \DomainException('Interactive input ended before completing the recipe.');
            }
            return trim($answer);
        };
        $slugs = $params === [] ? array_values(DemoProductNutritionCatalog::SEEDERS) : [$params[0]];
        $saved = 0;
        $skipped = 0;
        foreach ($slugs as $slug) {
            try {
                $result = NutritionPopulateService::populate(
                    APPPATH . 'Database/Seeds/data/', $slug, $ask,
                    static fn(int $fdcId): array => $lookup->details($fdcId)
                );
                if ($result === 'saved') {
                    $saved++;
                    CLI::write('Saved sourced ingredients, recipe, and signed worksheet for ' . $slug, 'green');
                } else {
                    $skipped++;
                    CLI::write($slug . ': ' . $result . ' (existing data unchanged).', 'yellow');
                }
            } catch (\Throwable $error) {
                CLI::error($slug . ': ' . $error->getMessage());
                CLI::write('No partial data saved for this product.', 'yellow');
                return EXIT_ERROR;
            }
        }
        CLI::write('Done: ' . $saved . ' recipe(s) populated, ' . $skipped . ' skipped.', 'green');
        CLI::write('Run php spark populate to seed any newly approved recipes.', 'cyan');
        return EXIT_SUCCESS;
    }

    private static function hasInteractiveInput(): bool
    {
        if (function_exists('stream_isatty')) {
            return stream_isatty(STDIN);
        }
        if (function_exists('posix_isatty')) {
            return posix_isatty(STDIN);
        }
        return true;
    }
}
