<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class MigrateAll extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'cafeteria:migrate';
    protected $description = 'Runs every pending migration from all namespaces with detailed verification output.';
    protected $usage = 'migrate [options]';
    protected $options = [
        '-g' => 'Set the database group passed to CodeIgniter migration runner.',
        '--all' => 'Always enabled by this project wrapper so all namespaces are migrated.',
    ];

    public function run(array $params): int
    {
        try {
            $this->banner('🗄️  DATABASE MIGRATION');
            $this->step('🔎', 'Scanning application migration files...', 'cyan');

            $migrations = $this->applicationMigrations();
            $before = $this->appliedVersions();
            $pending = array_values(array_filter(
                $migrations,
                static fn (array $migration): bool => ! isset($before[$migration['version']]),
            ));

            CLI::write(sprintf(
                '   Found %d app migration(s): %d applied, %d pending.',
                count($migrations),
                count($migrations) - count($pending),
                count($pending),
            ), 'white');

            foreach ($migrations as $index => $migration) {
                $applied = isset($before[$migration['version']]);
                $icon = $applied ? '⏭️ ' : '⏳';
                $state = $applied ? 'SKIPPED (already applied)' : 'PENDING';
                $color = $applied ? 'green' : 'yellow';
                CLI::write(sprintf(
                    '   %s [%d/%d] %s — %s',
                    $icon,
                    $index + 1,
                    count($migrations),
                    $migration['file'],
                    $state,
                ), $color);
            }

            if ($pending === []) {
                $this->step('✨', 'No pending app migrations. Existing migrations are skipped; checking all namespaces for anything new...', 'green');
            } else {
                $this->step('🚀', sprintf('Applying %d pending app migration(s) and checking all namespaces...', count($pending)), 'yellow');
            }

            $nativeParams = $this->nativeParams($params);
            $result = $this->call('migrate', $nativeParams);
            if (is_int($result) && $result !== EXIT_SUCCESS) {
                CLI::error('❌ CodeIgniter migration runner returned a failure status.');
                return $result;
            }

            $this->step('🔐', 'Verifying migration history...', 'cyan');
            $after = $this->appliedVersions();
            $missing = [];

            foreach ($migrations as $migration) {
                if (! isset($after[$migration['version']])) {
                    $missing[] = $migration['file'];
                    CLI::write('   ❌ Missing: ' . $migration['file'], 'red');
                    continue;
                }

                $wasPending = ! isset($before[$migration['version']]);
                CLI::write(
                    '   ' . ($wasPending ? '✅ APPLIED: ' : '⏭️  SKIPPED: ') . $migration['file'],
                    'green',
                );
            }

            if ($missing !== []) {
                CLI::newLine();
                CLI::error(sprintf('❌ Migration verification failed: %d app migration(s) are still pending.', count($missing)));
                return EXIT_ERROR;
            }

            CLI::newLine();
            $this->success(sprintf(
                '🎉 Migration complete — %d app migration(s) verified. Existing migrations skipped. No pending migrations.',
                count($migrations),
            ));

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::error('❌ Migration failed: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }

    /**
     * @return list<array{version:string,file:string}>
     */
    private function applicationMigrations(): array
    {
        $files = glob(APPPATH . 'Database/Migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        $migrations = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^(\d{4}-\d{2}-\d{2}-\d{6})_.+\.php$/', $basename, $matches) !== 1) {
                continue;
            }

            $migrations[] = [
                'version' => $matches[1],
                'file' => $basename,
            ];
        }

        return $migrations;
    }

    /**
     * @return array<string,true>
     */
    private function appliedVersions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('migrations')) {
            return [];
        }

        $rows = $db->table('migrations')
            ->select('version, namespace')
            ->get()
            ->getResultArray();
        $versions = [];

        foreach ($rows as $row) {
            $namespace = (string) ($row['namespace'] ?? '');
            if ($namespace !== '' && $namespace !== APP_NAMESPACE) {
                continue;
            }

            $versions[(string) $row['version']] = true;
        }

        return $versions;
    }

    /**
     * @return array<int|string,string|null>
     */
    private function nativeParams(array $params): array
    {
        $native = ['--all'];

        for ($i = 0, $count = count($params); $i < $count; $i++) {
            $value = (string) $params[$i];
            if ($value === '--all' || $value === '-n' || $value === '--namespace') {
                if (($value === '-n' || $value === '--namespace') && isset($params[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $native[] = $value;
        }

        return $native;
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
