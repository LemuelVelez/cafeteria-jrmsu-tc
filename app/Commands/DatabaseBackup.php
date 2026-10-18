<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\DatabaseBackupService;
class DatabaseBackup extends BaseCommand
{
    protected $group='Database';protected $name='db:backup';protected $description='Create compressed, checked database backup outside webroot.';
    public function run(array $params): void
    {
        try{$name=(new DatabaseBackupService())->create();CLI::write('Backup completed and SHA-256 verified: '.$name,'green');}
        catch(\Throwable $e){CLI::error('Backup failed: '.$e->getMessage());exit(1);}
    }
}
