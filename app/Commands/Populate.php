<?php

namespace App\Commands;

use App\Services\DemoProductNutritionCatalog;
use App\Services\NutritionLookupService;
use App\Services\NutritionPopulateService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Collects real kitchen measurements and writes all three source CSV files. */
final class Populate extends BaseCommand
{
    protected $group = 'Cafeteria';
    protected $name = 'populate';
    protected $description = 'Interactively populate sourced ingredients, recipes, and approved kitchen worksheet CSVs.';
    protected $usage = 'populate [product-slug]';

    public function run(array $params): int
    {
        if (count($params) > 1 || ($params !== []
            && ! in_array($params[0], DemoProductNutritionCatalog::SEEDERS, true))) {
            CLI::error('Usage: php spark ' . $this->usage);
            return EXIT_ERROR;
        }
        if (! defined('STDIN') || ! self::hasInteractiveInput()) {
            CLI::error('This command needs real kitchen answers from an interactive terminal. No CSVs changed.');
            return EXIT_ERROR;
        }
        CLI::write('This command NEVER invents recipe measurements, food sources, or kitchen approval.', 'yellow');
        CLI::write('A verified USDA FDC record and measured recipe quantities are required.', 'yellow');
        CLI::write('Three CSVs are saved together only when one product passes source/recipe validation.', 'yellow');
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
                    CLI::write($slug . ': ' . $result . ' (existing data left unchanged).', 'yellow');
                }
            } catch (\Throwable $error) {
                CLI::error($slug . ': ' . $error->getMessage());
                CLI::write('No partial data was saved for this product. Already completed products are retained.', 'yellow');
                return EXIT_ERROR;
            }
        }
        CLI::write('Done: ' . $saved . ' product(s) populated, ' . $skipped . ' skipped.', 'green');
        CLI::write('Next: php spark seed; php spark nutrition:catalog-audit', 'cyan');
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
