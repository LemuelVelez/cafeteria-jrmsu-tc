<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\AccountEmailService;
use App\Services\AuditLogService;
use App\Services\StockLevelPolicy;

class LowStockDigest extends BaseCommand
{
    protected $group='Inventory';
    protected $name='inventory:low-stock-digest';
    protected $description='Email the once-daily low-stock summary to active admins and cashiers, if enabled.';
    public function run(array $params): void
    {
        $db=db_connect();
        if ((string)($db->table('settings')->where('setting_key','low_stock_email_digest')->get()->getRow('setting_value')??'0')!=='1') {CLI::write('Low-stock email digest is disabled.');return;}
        $lock=$db->query("SELECT GET_LOCK('cafeteria.stock-digest',0) held")->getRow('held');
        if ((int)$lock!==1){CLI::write('Digest already running.');return;}
        try {
            $today=date('Y-m-d');
            $last=(string)($db->table('settings')->where('setting_key','low_stock_digest_last_at')->get()->getRow('setting_value')??'');
            if($last===$today){CLI::write('Digest already sent today.');return;}
            $all=$db->table('products')->select('name,stock,reorder_level,max_stock')->where('deleted_at',null)->orderBy('name')->get()->getResultArray();
            $stockSettings=StockLevelPolicy::settings($db);
            $products=array_values(array_filter($all,static fn(array $p):bool=>in_array(StockLevelPolicy::forProduct($p,$stockSettings)['level'],['red','empty'],true)));
            if (!$products){CLI::write('No low-stock products to report.');return;}
            $recipients=$db->table('users')->select('id,email')->whereIn('role',['admin','cashier'])->where('status','active')->get()->getResultArray();
            $mail=new AccountEmailService();$sent=0;
            foreach($recipients as $recipient)if($mail->sendStockDigest($recipient,$products))$sent++;
            if ($recipients && $sent!==count($recipients))throw new \RuntimeException('Not every low-stock email was delivered. Retry after fixing the email transport.');
            $settings=new \App\Models\SettingModel();
            $settings->setValue('low_stock_digest_last_at',$today);
            (new AuditLogService($db))->record('low_stock_digest','inventory',null,['products'=>count($products),'recipients'=>$sent]);
            CLI::write('Daily low-stock digest sent to '.$sent.' staff members.');
        } finally { $db->query("SELECT RELEASE_LOCK('cafeteria.stock-digest')"); }
    }
}
