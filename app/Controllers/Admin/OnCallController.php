<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\DailyInventoryService;
use App\Services\OnCallWagePolicy;
use App\Services\AuditLogService;
use App\Controllers\Admin\ReportController;
use Throwable;

class OnCallController extends BaseController
{
    private function data(): array
    {
        $from=(string)($this->request->getGet('from')?:date('Y-m-01'));
        $to=(string)($this->request->getGet('to')?:date('Y-m-d'));
        DailyInventoryService::validateDate($from);DailyInventoryService::validateDate($to);
        if($from>$to)throw new \DomainException('Invalid date range.');
        $db=db_connect();
        $rows=$db->query('SELECT e.*,u.name recorder_name FROM on_call_work_entries e LEFT JOIN users u ON u.id=e.recorded_by WHERE work_date>=? AND work_date<=? ORDER BY work_date DESC,e.id DESC',[$from,$to])->getResultArray();
        $rate=$db->table('settings')->where('setting_key','on_call_hourly_rate')->get()->getRow('setting_value');
        return ['from'=>$from,'to'=>$to,'rows'=>$rows,'rate'=>$rate,'total'=>array_sum(array_column($rows,'total_payment'))];
    }
    public function index(): string { return $this->render('admin/on_call/index',$this->data()+['title'=>'On-call wages']); }
    public function printable(): string { return view('admin/on_call/print',$this->data()); }
    public function export()
    {
        $d=$this->data();$stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");
        $put=static fn($row)=>fputcsv($stream,array_map([ReportController::class,'safeCsvCell'],$row),',','"','');
        $put(['On-call staff wages',$d['from'].' to '.$d['to']]);
        $put(['Staff','Date','Hours','Additional minutes','Rate snapshot','Total payment','Remarks','Recorded by']);
        foreach($d['rows'] as $r)$put([$r['staff_name'],$r['work_date'],$r['hours_rendered'],$r['additional_minutes'],$r['hourly_rate'],$r['total_payment'],$r['remarks'],$r['recorder_name']]);
        $put(['GRAND TOTAL','','','','',$d['total']]);
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        return $this->response->setHeader('Content-Type','text/csv; charset=UTF-8')->setHeader('Content-Disposition','attachment; filename="on-call-'.$d['from'].'-'.$d['to'].'.csv"')->setBody($csv?:'');
    }
    public function save()
    {
        $name=trim((string)$this->request->getPost('staff_name'));
        $date=(string)$this->request->getPost('work_date');
        $hours=filter_var($this->request->getPost('hours_rendered'),FILTER_VALIDATE_INT);
        $minutes=filter_var($this->request->getPost('additional_minutes'),FILTER_VALIDATE_INT);
        $remarks=trim((string)$this->request->getPost('remarks'));
        $db=db_connect();$db->transBegin();
        try {
            DailyInventoryService::validateDate($date);
            if($name===''||mb_strlen($name)>150||mb_strlen($remarks)>1000||$hours===false||$minutes===false)throw new \DomainException('Enter a valid name, hours and minutes.');
            $rate=$db->table('settings')->where('setting_key','on_call_hourly_rate')->get()->getRow('setting_value');
            $payment=OnCallWagePolicy::calculate((int)$hours,(int)$minutes,$rate);
            $actor=(int)(session()->get('user')['id']??0);
            $db->table('on_call_work_entries')->insert(['staff_name'=>$name,'work_date'=>$date,'hours_rendered'=>$hours,'additional_minutes'=>$minutes,'hourly_rate'=>$rate,'total_payment'=>$payment,'remarks'=>$remarks,'recorded_by'=>$actor,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('on_call_entry_created','on_call_work_entry',(int)$db->insertID(),['staff_name'=>$name,'work_date'=>$date,'hours'=>$hours,'minutes'=>$minutes,'rate'=>$rate,'total'=>$payment],$actor);
            if(!$db->transStatus())throw new \RuntimeException('Could not save work entry.');
            $db->transCommit();return redirect()->to('/admin/on-call')->with('success','On-call payment recorded.');
        }catch(Throwable $e){$db->transRollback();return redirect()->to('/admin/on-call')->with('error',$e->getMessage());}
    }
}
