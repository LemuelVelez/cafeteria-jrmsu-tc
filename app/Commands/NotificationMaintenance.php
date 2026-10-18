<?php
namespace App\Commands;

use App\Services\AuditLogService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class NotificationMaintenance extends BaseCommand
{
    protected $group='Cafeteria';
    protected $name='notifications:prune';
    protected $description='Remove read notifications older than the configured retention window.';

    public function run(array $params)
    {
        $db=db_connect();
        $days=max(1,min(3650,(int) ($db->table('settings')->where('setting_key','notification_read_retention_days')->get()->getRow('setting_value') ?? 90)));
        $cutoff=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Manila')))->modify('-'.$days.' days')->format('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $builder=$db->table('notifications')->where('read_at IS NOT NULL',null,false)->where('read_at <',$cutoff);
            $deleted=$builder->countAllResults();
            $db->table('notifications')->where('read_at IS NOT NULL',null,false)->where('read_at <',$cutoff)->delete();
            (new AuditLogService($db))->record('notification_prune','notification',null,['count'=>$deleted,'retention_days'=>$days]);
            if (!$db->transStatus())throw new \RuntimeException('Notification cleanup failed.');
            $db->transCommit();
            CLI::write('Pruned '.$deleted.' read notifications.');
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
}
