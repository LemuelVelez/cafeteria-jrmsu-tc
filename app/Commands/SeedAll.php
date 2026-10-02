<?php

namespace App\Commands;

use App\Database\Seeds\DatabaseSeeder;
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
    protected $usage = 'seed';

    public function run(array $params): int
    {
        try {
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

            $seeders = DatabaseSeeder::seeders();
            if ($seeders === []) {
                CLI::write('✨ No registered seeders. Nothing to run.', 'green');
                return EXIT_SUCCESS;
            }

            $existingSeeders = $this->existingSeederFiles();
            $unregistered = array_values(array_diff($existingSeeders, $seeders));
            if ($unregistered !== []) {
                foreach ($unregistered as $seeder) {
                    CLI::write('   ⚠️ Unregistered seeder: ' . $seeder . '.php', 'yellow');
                }
                throw new RuntimeException('Every application seeder must be registered in DatabaseSeeder::SEEDERS.');
            }

            $this->step('🔎', sprintf('Checking %d registered seeder(s)...', count($seeders)), 'cyan');
            $pending = [];
            $skipped = 0;

            foreach ($seeders as $index => $seeder) {
                $position = $index + 1;
                $path = APPPATH . 'Database/Seeds/' . $seeder . '.php';
                if (! is_file($path)) {
                    throw new RuntimeException('Registered seeder file is missing: ' . $seeder . '.php');
                }

                $record = $this->seederRun($db, $seeder);
                if ($record !== null) {
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
            if ($pending === []) {
                $this->success(sprintf(
                    '🎉 No pending seeders — %d existing seeder(s) skipped. Database seed state is current.',
                    $skipped,
                ));
                return EXIT_SUCCESS;
            }

            $this->step('🚀', sprintf('Running %d pending seeder(s); %d existing seeder(s) will remain skipped.', count($pending), $skipped), 'yellow');
            CLI::newLine();

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
            $this->step('🔐', 'Final verification: checking that every registered seeder is recorded...', 'cyan');
            $missing = [];
            foreach ($seeders as $seeder) {
                if ($this->seederRun($db, $seeder) === null) {
                    $missing[] = $seeder;
                    CLI::write('   ❌ Pending: ' . $seeder, 'red');
                    continue;
                }
                CLI::write('   ✅ Current: ' . $seeder, 'green');
            }

            if ($missing !== []) {
                CLI::error(sprintf('❌ Seeder verification failed: %d seeder(s) are still pending.', count($missing)));
                return EXIT_ERROR;
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

    /**
     * @return array<string,mixed>|null
     */
    private function seederRun(BaseConnection $db, string $seeder): ?array
    {
        $row = $db->table('seeder_runs')
            ->where('seeder_name', $seeder)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    private function recordSeederRun(BaseConnection $db, string $seeder, string $mode): void
    {
        if ($this->seederRun($db, $seeder) !== null) {
            return;
        }

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
