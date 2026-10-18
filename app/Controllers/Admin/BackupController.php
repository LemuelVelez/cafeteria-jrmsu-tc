<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Services\DatabaseBackupService;
use App\Services\AuditLogService;
class BackupController extends BaseController
{
    public function index(): string {return $this->render('admin/backups/index',['title'=>'Database backups','files'=>(new DatabaseBackupService())->files()]);}
    public function create()
    {
        try{$name=(new DatabaseBackupService())->create();return redirect()->to('/admin/backups')->with('success','Backup created: '.$name);}
        catch(\Throwable $e){log_message('error','Backup generation failed: '.$e->getMessage());return redirect()->to('/admin/backups')->with('error','Backup failed. Verify database tools and storage.');}
    }
    public function download(string $name)
    {
        if(!preg_match('/^cafeteria-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql\.gz$/',$name))return redirect()->to('/admin/backups');
        $path=DatabaseBackupService::DIRECTORY.$name;
        if(!DatabaseBackupService::checksum($path))return redirect()->to('/admin/backups')->with('error','Backup checksum failed.');
        (new AuditLogService())->record('database_backup_download','backup',null,['file'=>$name]);
        return $this->response->download($path,null)->setFileName($name);
    }
}
