<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AuditLogService;
use App\Services\DatabaseBackupService;

class BackupController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/backups/index', [
            'title' => 'Database backups',
            'files' => (new DatabaseBackupService())->files(),
        ]);
    }

    public function create()
    {
        try {
            $name = (new DatabaseBackupService())->create();
            return redirect()->to(base_url('admin/backups'))
                ->with('success', 'Backup created and verified: ' . $name);
        } catch (\Throwable $e) {
            log_message('error', 'Backup generation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(base_url('admin/backups'))->with(
                'error',
                'Backup could not be created. Check database access, writable/backups permissions, and server logs.'
            );
        }
    }

    public function download(string $name)
    {
        if (! preg_match('/^cafeteria-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql\.gz$/D', $name)) {
            return redirect()->to(base_url('admin/backups'));
        }
        $path = DatabaseBackupService::DIRECTORY . $name;
        if (! DatabaseBackupService::checksum($path)) {
            return redirect()->to(base_url('admin/backups'))
                ->with('error', 'Backup checksum verification failed.');
        }
        (new AuditLogService())->record('database_backup_download', 'backup', null, ['file' => $name]);
        return $this->response->download($path, null)->setFileName($name);
    }
}
