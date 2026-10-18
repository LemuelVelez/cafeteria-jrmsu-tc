<?php
namespace App\Services;
final class DatabaseBackupService
{
    public const DIRECTORY = WRITEPATH.'backups/';
    public static function checksum(string $path): bool
    {
        return is_file($path) && is_file($path.'.sha256') && hash_equals(trim((string)file_get_contents($path.'.sha256')),hash_file('sha256',$path));
    }
    public function create(): string
    {
        $db=db_connect();$db->initialize();
        $name='cafeteria-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql.gz';
        if(!is_dir(self::DIRECTORY))mkdir(self::DIRECTORY,0700,true);
        $path=self::DIRECTORY.$name;
        $stream=gzopen($path,'wb9');
        if (!$stream)throw new \RuntimeException('Backup directory unavailable.');
        try {
            $mysqldump=trim((string)@shell_exec('command -v mysqldump 2>/dev/null'));
            if($mysqldump!=='') $this->dumpProcess($mysqldump,$db,$stream);
            else $this->dumpPhp($db,$stream);
        }catch(\Throwable $e){gzclose($stream);@unlink($path);throw $e;}
        gzclose($stream);
        if(!is_file($path)||filesize($path)<15)throw new \RuntimeException('Backup is incomplete.');
        file_put_contents($path.'.sha256',hash_file('sha256',$path),LOCK_EX);
        @chmod($path,0600);@chmod($path.'.sha256',0600);
        $this->retention();
        $this->uploadS3($path);
        (new AuditLogService())->record('database_backup','backup',null,['file'=>$name,'sha256'=>hash_file('sha256',$path)]);
        return $name;
    }
    private function dumpProcess(string $binary,object $db,$output): void
    {
        $tmp=tempnam(sys_get_temp_dir(),'cafe-credentials-');
        if ($tmp===false)throw new \RuntimeException('Could not create credential file.');
        @chmod($tmp,0600);
        $dsn=$db->hostname;
        $credentials="[client]\nuser=".json_encode((string)$db->username)."\npassword=".json_encode((string)$db->password)."\nhost=".json_encode((string)$dsn)."\n";
        file_put_contents($tmp,$credentials);
        $cmd=[$binary,'--defaults-extra-file='.$tmp,'--single-transaction','--routines','--triggers','--default-character-set=utf8mb4','--no-tablespaces',(string)$db->database];
        try {
            $process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new \RuntimeException('Could not run mysqldump.');
            fclose($pipes[0]);
            while(!feof($pipes[1])){ $part=fread($pipes[1],32768); if($part!==false && $part!=='')gzwrite($output,$part); }
            fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
            if($exit!==0)throw new \RuntimeException('mysqldump failed: '.mb_substr($error,0,200));
        } finally { @unlink($tmp); }
    }
    private function dumpPhp(object $db,$output): void
    {
        gzwrite($output,"-- Portable tables-only backup; mysqldump is recommended for procedures/triggers.\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach($db->query('SHOW FULL TABLES WHERE Table_type=\'BASE TABLE\'')->getResultArray() as $table) {
            $name=(string)reset($table);
            if (!preg_match('/^[a-zA-Z0-9_]+$/',$name))throw new \RuntimeException('Unsafe table name.');
            $structure=$db->query('SHOW CREATE TABLE `'.$name.'`')->getRowArray();
            gzwrite($output,'DROP TABLE IF EXISTS `'.$name.'`;' ."\n".(string)($structure['Create Table']??'').";\n");
            $rows=$db->query('SELECT * FROM `'.$name.'`')->getResultArray();
            foreach($rows as $row){$values=array_map(static fn($v)=>$db->escape($v),array_values($row));gzwrite($output,'INSERT INTO `'.$name.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',$values).');' ."\n");}
        }
        gzwrite($output,"SET FOREIGN_KEY_CHECKS=1;\n");
    }
    public function files(): array
    {
        if(!is_dir(self::DIRECTORY))return [];
        $names=array_filter(array_map('basename',glob(self::DIRECTORY.'cafeteria-*.sql.gz')?:[]));rsort($names);
        return array_map(static fn($n)=>['name'=>$n,'size'=>filesize(self::DIRECTORY.$n),'valid'=>self::checksum(self::DIRECTORY.$n)],$names);
    }
    public function retention(): void
    {
        $keepDaily=max(1,(int)$this->setting('backup_keep_daily','7'));
        $keepWeekly=max(0,(int)$this->setting('backup_keep_weekly','4'));
        $keepMonthly=max(0,(int)$this->setting('backup_keep_monthly','6'));
        $days=[];$weeks=[];$months=[];
        foreach($this->files() as $file){
            $name=$file['name'];$date=\DateTimeImmutable::createFromFormat('!Ymd-His',substr($name,10,15));
            if(!$date)continue;
            $daily=$date->format('Y-m-d');$week=$date->format('o-W');$month=$date->format('Y-m');
            if(!isset($days[$daily])&&count($days)<$keepDaily)$days[$daily]=$name;
            elseif(!isset($weeks[$week])&&count($weeks)<$keepWeekly)$weeks[$week]=$name;
            elseif(!isset($months[$month])&&count($months)<$keepMonthly)$months[$month]=$name;
        }
        $keep=array_merge(array_values($days),array_values($weeks),array_values($months));
        foreach($this->files() as $file)if(!in_array($file['name'],$keep,true)){@unlink(self::DIRECTORY.$file['name']);@unlink(self::DIRECTORY.$file['name'].'.sha256');}
    }
    private function setting(string $key, string $fallback): string
    {
        return (string) (db_connect()->table('settings')->where('setting_key', $key)->get()->getRow('setting_value') ?? $fallback);
    }

    private function uploadS3(string $path): void
    {
        $bucket=(string)env('AWS_S3_BUCKET','');
        if($bucket==='')return;
        if(!class_exists('Aws\S3\S3Client'))throw new \RuntimeException('Install the AWS SDK for offsite backup.');
        $client=new \Aws\S3\S3Client(['version'=>'latest','region'=>(string)env('AWS_REGION','ap-southeast-1')]);
        $client->putObject(['Bucket'=>$bucket,'Key'=>'cafeteria-backups/'.basename($path),'Body'=>fopen($path,'rb'),'ServerSideEncryption'=>'AES256']);
    }
}
