<?php

namespace App\Commands;

use App\Database\Seeds\DatabaseSeeder;
use App\Database\Seeds\DemoRecipePreviewSeeder;
use App\Database\Seeds\IngredientSeeder;
use App\Database\Seeds\RecipeSeeder;
use App\Services\DemoProductNutritionCatalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

class SeedAll extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'seed';
    protected $description = 'Runs pending application seeders once and skips seeders already recorded or already satisfied.';
    protected $usage = 'seed [strict]';

    public function run(array $params): int
    {
        try {
            // Standard setup includes required seeders and only approved
            // recipes. Strict mode additionally requires every demo recipe.
            if (array_diff($params, ['strict']) !== []) {
                CLI::error('Usage: php spark ' . $this->usage);
                return EXIT_ERROR;
            }
            $strict = in_array('strict', $params, true);
            $this->banner('🌱 DATABASE SEEDING');

            $this->step('🧭', 'Pre-flight: checking for pending migrations first...', 'cyan');
            $migrationResult = $this->call('cafeteria:migrate');
            if (is_int($migrationResult) && $migrationResult !== EXIT_SUCCESS) {
                CLI::error('❌ Seeding stopped because database migrations are not complete.');
                return $migrationResult;
            }

            $db = db_connect();
            if (! $db->tableExists('seeder_runs')) {
                throw new RuntimeException('Seeder tracking table is missing. Run php spark migrate first.');
            }

            $registered = DatabaseSeeder::seeders();
            if ($registered === []) {
                CLI::write('✨ No registered seeders. Nothing to run.', 'green');
                return EXIT_SUCCESS;
            }

            $existingSeeders = $this->existingSeederFiles();
            $unregistered = DatabaseSeeder::unregisteredSeeders($existingSeeders);
            if ($unregistered !== []) {
                foreach ($unregistered as $seeder) {
                    CLI::write('   ⚠️ Unregistered seeder: ' . $seeder . '.php', 'yellow');
                }
                throw new RuntimeException('Every application seeder must be registered or explicitly manual-only in DatabaseSeeder.');
            }

            $sourceRows = IngredientSeeder::rows();
            $recipeRows = RecipeSeeder::rows();
            $worksheet = DemoProductNutritionCatalog::worksheet();
            $readySlugs = [];
            foreach (DemoProductNutritionCatalog::SEEDERS as $slug) {
                if (DemoProductNutritionCatalog::assess($slug, $sourceRows, $recipeRows, $worksheet)['ready']) {
                    $readySlugs[] = $slug;
                }
            }
            $approvedRecipes = count($readySlugs);
            $requiredRecipes = count(DemoProductNutritionCatalog::SEEDERS);
            if ($strict && $approvedRecipes !== $requiredRecipes) {
                CLI::error(sprintf('Strict nutrition acceptance is not ready: %d/%d signed, measured recipes.',
                    $approvedRecipes, $requiredRecipes));
                CLI::write('Enter authentic kitchen-approved quantities in ingredients.csv, recipes.csv', 'yellow');
                CLI::write('and recipe_measurement_workbook.csv; run php spark nutrition:catalog-audit.', 'yellow');
                return EXIT_ERROR;
            }

            $seeders = DatabaseSeeder::runnableSeeders($readySlugs, $strict);
            $this->step('🔎', sprintf('Checking %d applicable seeder(s)...', count($seeders)), 'cyan');
            // Fetch seeder history once. Per-seeder SELECT calls (and then
            // repeating them during final verification) are very expensive on
            // a high-latency database connection.
            $recordedRuns = array_column(
                $db->table('seeder_runs')->get()->getResultArray(), null, 'seeder_name',
            );
            $pending = [];
            $skipped = 0;

            foreach ($seeders as $index => $seeder) {
                $position = $index + 1;
                $path = APPPATH . 'Database/Seeds/' . $seeder . '.php';
                if (! is_file($path)) {
                    throw new RuntimeException('Registered seeder file is missing: ' . $seeder . '.php');
                }

                $record = $recordedRuns[$seeder] ?? null;
                $productSlug = DemoProductNutritionCatalog::slugForSeeder($seeder);

                if ($record !== null) {
                    // A recipe seeder marker is not enough: an approved product
                    // must still match its sourced database recipe. Never
                    // overwrite previously applied administrative changes.
                    if ($productSlug !== null && ! DatabaseSeeder::isSatisfied($seeder, $db)) {
                        throw new RuntimeException($seeder . ' was previously recorded, but the approved '
                            . 'recipe does not match the database. Reconcile it with an audited '
                            . 'append-only correction; automatic replay is unsafe.');
                    }
                    $skipped++;
                    CLI::write(sprintf(
                        '   ⏭️  [%d/%d] %s — SKIPPED (already seeded)',
                        $position,
                        count($seeders),
                        $seeder,
                    ), 'green');
                    continue;
                }

                if (DatabaseSeeder::isSatisfied($seeder, $db)) {
                    $this->recordSeederRun($db, $seeder, 'adopted_existing');
                    $recordedRuns[$seeder] = true;
                    $skipped++;
                    CLI::write(sprintf(
                        '   ⏭️  [%d/%d] %s — SKIPPED (existing seed data detected)',
                        $position,
                        count($seeders),
                        $seeder,
                    ), 'green');
                    continue;
                }

                $pending[] = $seeder;
                CLI::write(sprintf(
                    '   ⏳ [%d/%d] %s — PENDING',
                    $position,
                    count($seeders),
                    $seeder,
                ), 'yellow');
            }

            CLI::newLine();
            if ($pending !== []) {
                $this->step('🚀', sprintf('Running %d pending seeder(s); %d existing seeder(s) will remain skipped.', count($pending), $skipped), 'yellow');
                CLI::newLine();
            } else {
                CLI::write('No runnable seeders remain.', 'green');
            }

            $seederRunner = Database::seeder();
            $seederRunner->setSilent(true);

            foreach ($pending as $index => $seeder) {
                $position = $index + 1;
                CLI::write(sprintf('⏳ [%d/%d] Running %s...', $position, count($pending), $seeder), 'yellow');
                $startedAt = microtime(true);

                $seederRunner->call($seeder);

                if (! DatabaseSeeder::isSatisfied($seeder, $db)) {
                    throw new RuntimeException($seeder . ' finished but its expected seed state could not be verified.');
                }

                $this->recordSeederRun($db, $seeder, 'executed');
                $recordedRuns[$seeder] = true;
                $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
                CLI::write(sprintf(
                    '✅ [%d/%d] %s completed and recorded (%d ms)',
                    $position,
                    count($pending),
                    $seeder,
                    $elapsedMs,
                ), 'green');
            }

            CLI::newLine();
            $this->step('🔐', 'Final verification: checking all applicable seeders...', 'cyan');
            // Verify the committed markers in one query, without querying
            // once for every seed file or relying only on in-memory state.
            $storedNames = array_fill_keys(array_column(
                $db->table('seeder_runs')->select('seeder_name')
                    ->whereIn('seeder_name', $seeders)->get()->getResultArray(),
                'seeder_name',
            ), true);
            $missing = [];
            foreach ($seeders as $seeder) {
                if (! isset($storedNames[$seeder])) {
                    $missing[] = $seeder;
                    CLI::write('   ❌ Pending: ' . $seeder, 'red');
                    continue;
                }
                CLI::write('   ✅ Current: ' . $seeder, 'green');
            }

            if ($missing !== []) {
                CLI::error(sprintf('❌ Seeder verification failed: %d required seeder(s) are unrecorded.', count($missing)));
                return EXIT_ERROR;
            }

            CLI::newLine();
            $demoCoverage = DemoRecipePreviewSeeder::coverage($db);
            CLI::write(sprintf('Demo product recipes populated: %d/%d.',
                $demoCoverage, $requiredRecipes), $demoCoverage === $requiredRecipes ? 'green' : 'yellow');
            if ($demoCoverage === $requiredRecipes) {
                CLI::write('No missing demo recipes. Any synthetic previews are not actual kitchen recipes.', 'green');
            } else {
                CLI::write('Missing demo product recipes: ' . ($requiredRecipes - $demoCoverage) . '.', 'yellow');
            }
            CLI::write(sprintf('Independently kitchen-approved source recipes: %d/%d.',
                $approvedRecipes, $requiredRecipes), 'cyan');
            if ($approvedRecipes < $requiredRecipes) {
                CLI::write('Demo estimates are not signed or independently verified.', 'yellow');
                CLI::write('For source validation: php spark nutrition:catalog-audit', 'cyan');
            }

            CLI::newLine();
            $this->success(sprintf(
                '🎉 Seeding complete — %d pending seeder(s) applied, %d existing seeder(s) skipped. No pending seeders.',
                count($pending),
                $skipped,
            ));

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::error('❌ Seeding failed: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }

    /**
     * @return list<string>
     */
    private function existingSeederFiles(): array
    {
        $files = glob(APPPATH . 'Database/Seeds/*Seeder.php') ?: [];
        $seeders = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($name === 'DatabaseSeeder') {
                continue;
            }
            $seeders[] = $name;
        }

        sort($seeders, SORT_STRING);
        return $seeders;
    }

    private function recordSeederRun(BaseConnection $db, string $seeder, string $mode): void
    {
        // The caller already queried seeder_runs as a batch and only calls
        // this method for an unrecorded seeder. The database uniqueness
        // constraint still protects against concurrent registrations.
        $path = APPPATH . 'Database/Seeds/' . $seeder . '.php';
        $hash = is_file($path) ? hash_file('sha256', $path) : null;

        $db->table('seeder_runs')->insert([
            'seeder_name' => $seeder,
            'file_hash' => $hash ?: null,
            'run_mode' => $mode,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($db->affectedRows() !== 1) {
            throw new RuntimeException('Unable to record seeder state for ' . $seeder . '.');
        }
    }

    private function banner(string $title): void
    {
        CLI::newLine();
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'cyan');
        CLI::write('  ' . $title, 'cyan');
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'cyan');
        CLI::newLine();
    }

    private function step(string $icon, string $message, string $color): void
    {
        CLI::write($icon . ' ' . $message, $color);
    }

    private function success(string $message): void
    {
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'green');
        CLI::write($message, 'green');
        CLI::write('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'green');
    }
}
