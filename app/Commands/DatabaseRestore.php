<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\DatabaseBackupService;
use App\Services\AuditLogService;
class DatabaseRestore extends BaseCommand
{
    protected $group='Database';protected $name='db:restore';protected $description='Verify backup, take a safety snapshot, and restore (CLI only).';
    public function run(array $params): void
    {
        $name=basename($params[0]??'');
        if(!preg_match('/^cafeteria-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql\.gz$/',$name)){CLI::error('Specify an existing backup filename.');return;}
        $path=DatabaseBackupService::DIRECTORY.$name;
        if(!DatabaseBackupService::checksum($path)){CLI::error('Missing file or invalid SHA-256 checksum.');return;}
        $confirmation=CLI::prompt('Type RESTORE '. $name . ' to proceed');
        if($confirmation !== 'RESTORE '.$name){CLI::error('Restore cancelled.');return;}
        try {
            (new DatabaseBackupService())->create(); // mandatory pre-restore snapshot
            $db=db_connect();$db->initialize();
            $binary=trim((string)@shell_exec('command -v mysql 2>/dev/null'));
            if ($binary==='')throw new \RuntimeException('mysql CLI is required for safe import of routines and triggers.');
            $tmp=tempnam(sys_get_temp_dir(),'cafe-restore-');
            if($tmp===false)throw new \RuntimeException('Unable to create credential file.');
            @chmod($tmp,0600);
            file_put_contents($tmp,"[client]\nuser=".json_encode((string)$db->username)."\npassword=".json_encode((string)$db->password)."\nhost=".json_encode((string)$db->hostname)."\n");
            try {
                $process=proc_open([$binary,'--defaults-extra-file='.$tmp,(string)$db->database],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                if(!is_resource($process))throw new \RuntimeException('mysql could not start.');
                $input=gzopen($path,'rb');
                while(!gzeof($input)){ $data=gzread($input,32768);if($data!==false && $data!=='')fwrite($pipes[0],$data); }
                gzclose($input);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
                if(proc_close($process)!==0)throw new \RuntimeException('Restore failed: '.mb_substr($error,0,200));
            }finally{@unlink($tmp);}
            (new AuditLogService())->record('database_restore','backup',null,['file'=>$name]);
            CLI::write('Restore completed: '.$name,'green');
        }catch(\Throwable $e){CLI::error('Restore stopped: '.$e->getMessage());}
    }
}
