<?php

namespace App\Commands;

use App\Services\BackupRestoreDrillService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** The drill never imports into, or drops, the source database. */
final class DatabaseRestoreCheck extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'db:restore-check';
    protected $description = 'Test a newly created backup in an absent disposable _restore_check database.';

    public function run(array $params): void
    {
        try {
            $result = (new BackupRestoreDrillService())->run();
            CLI::write('Backup restored and verified: ' . $result['backup'], 'green');
            foreach ($result['counts'] as $table => $count) {
                CLI::write($table . ': ' . $count . ' row(s)');
            }
            CLI::write('Scratch database ' . $result['scratch'] . ' has been removed.', 'green');
        } catch (\Throwable $exception) {
            CLI::error('Backup restore drill failed: ' . $exception->getMessage());
            exit(1);
        }
    }
}
