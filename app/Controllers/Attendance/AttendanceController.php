<?php
namespace App\Controllers\Attendance;
use App\Controllers\BaseController;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
class AttendanceController extends BaseController
{
    private function admin(): bool {return (session()->get('user')['role']??'')==='admin';}
    public function index(): string
    {
        $db=db_connect();$id=(int)session()->get('user')['id'];
        $admin=$this->admin();
        return $this->render('attendance/index',['title'=>'Attendance','isAdmin'=>$admin,
            'users'=>$admin?$db->table('users')->select('id,name,role')->whereIn('role',['admin','cashier','rider'])->get()->getResultArray():[],
            'punches'=>$db->table('attendance_punches')->where('user_id',$id)->orderBy('at','DESC')->limit(40)->get()->getResultArray()]);
    }
    public function punch()
    {
        $id=(int)session()->get('user')['id'];$db=db_connect();$db->transBegin();
        try {
            $last=$db->query('SELECT kind FROM attendance_punches WHERE user_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$id])->getRowArray();
            $kind=($last['kind']??'out')==='out'?'in':'out';
            $db->table('attendance_punches')->insert(['user_id'=>$id,'kind'=>$kind,'at'=>date('Y-m-d H:i:s'),'method'=>'dashboard','ip_address'=>$this->request->getIPAddress()]);
            (new AuditLogService($db))->record('attendance_clock_'.$kind,'user',$id);
            if (!$db->transStatus())throw new \RuntimeException('Unable to save attendance.');
            $db->transCommit();return redirect()->to('/attendance')->with('success','Clocked '.$kind.'.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/attendance')->with('error',$e->getMessage());}
    }
    public function shift()
    {
        return $this->saveAdmin('attendance_shifts',['user_id'=>(int)$this->request->getPost('user_id'),'weekday'=>(int)$this->request->getPost('weekday'),
            'start_time'=>(string)$this->request->getPost('start_time'),'end_time'=>(string)$this->request->getPost('end_time'),'grace_minutes'=>(int)$this->request->getPost('grace_minutes')]);
    }
    public function rate()
    {
        return $this->saveAdmin('attendance_pay_rates',['user_id'=>(int)$this->request->getPost('user_id'),
            'hourly_rate'=>(float)$this->request->getPost('hourly_rate'),'effective_from'=>date('Y-m-d')]);
    }
    private function saveAdmin(string $table,array $values)
    {
        if (!$this->admin())return redirect()->to('/attendance');
        $db=db_connect();$db->transBegin();
        try {
            if($values['user_id']<1)throw new \DomainException('Invalid employee.');
            if($table==='attendance_shifts'){
                if($values['weekday']<1||$values['weekday']>7||!preg_match('/^\d\d:\d\d$/',$values['start_time'])||!preg_match('/^\d\d:\d\d$/',$values['end_time']))throw new \DomainException('Invalid shift schedule.');
                $existing=$db->table($table)->where(['user_id'=>$values['user_id'],'weekday'=>$values['weekday']])->get()->getRowArray();
                if($existing)$db->table($table)->where('id',$existing['id'])->update($values);else $db->table($table)->insert($values);
            }else{
                if($values['hourly_rate']<0)throw new \DomainException('Invalid hourly rate.');
                $db->table($table)->insert($values);
            }
            (new AuditLogService($db))->record('attendance_settings_change','user',$values['user_id'],['fields'=>array_keys($values)]);
            if(!$db->transStatus())throw new \RuntimeException('Unable to save payroll settings.');
            $db->transCommit();return redirect()->to('/admin/attendance')->with('success','Attendance settings saved.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/attendance')->with('error',$e->getMessage());}
    }
    public function correct(int $id)
    {
        if(!$this->admin())return redirect()->to('/attendance');
        $new=(string)$this->request->getPost('at');$reason=trim((string)$this->request->getPost('reason'));
        if (!$reason || !\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$new))return redirect()->to('/admin/attendance')->with('error','Valid time and correction reason required.');
        $db=db_connect();$db->transBegin();
        try{
            $old=$db->query('SELECT * FROM attendance_punches WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$old)throw new \DomainException('Punch not found.');
            $values=['at'=>str_replace('T',' ',$new).':00','edited_by'=>session()->get('user')['id'],'edit_reason'=>$reason];
            $db->table('attendance_punches')->where('id',$id)->update($values);
            (new AuditLogService($db))->recordChange('attendance_correction','attendance_punch',$id,['at'=>$old['at']],['at'=>$values['at']]);
            $db->transCommit();return redirect()->to('/admin/attendance')->with('success','Corrected.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/attendance')->with('error',$e->getMessage());}
    }
    public function report()
    {
        if(!$this->admin())return redirect()->to('/attendance');
        $from=(string)($this->request->getGet('from')?:date('Y-m-01'));$to=(string)($this->request->getGet('to')?:date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to)return redirect()->to('/admin/attendance')->with('error','Invalid date range.');
        $db=db_connect();$staff=$db->table('users')->select('id,name,role')->whereIn('role',['admin','cashier','rider'])->get()->getResultArray();
        $rows=[];
        foreach($staff as $person){
            $id=$person['id'];$punches=$db->table('attendance_punches')->where('user_id',$id)->where('at >=',$from.' 00:00:00')->where('at <=',$to.' 23:59:59')->orderBy('at')->get()->getResultArray();
            $rates=$db->table('attendance_pay_rates')->where('user_id',$id)->orderBy('effective_from','DESC')->get()->getResultArray();
            $rate=(float)($rates[0]['hourly_rate']??0);
            for($i=0;$i+1<count($punches);$i++){
                if($punches[$i]['kind']!=='in'||$punches[$i+1]['kind']!=='out')continue;
                $day=(int)date('N',strtotime($punches[$i]['at']));$shift=$db->table('attendance_shifts')->where(['user_id'=>$id,'weekday'=>$day])->get()->getRowArray();
                $calculated=AttendanceService::estimate($punches[$i]['at'],$punches[$i+1]['at'],$shift['start_time']??'08:00:00',$shift['end_time']??'17:00:00',$rate,(int)($shift['grace_minutes']??5),(float)env('ATTENDANCE_OVERTIME_PREMIUM',0.25),(float)env('ATTENDANCE_NIGHT_DIFFERENTIAL',0.10));
                $rows[]=array_merge(['employee'=>$person['name'],'date'=>substr($punches[$i]['at'],0,10)],$calculated);$i++;
            }
        }
        if($this->request->getGet('format')==='csv'){
            $f=fopen('php://temp','w+');if($rows){fputcsv($f,array_keys($rows[0]));foreach($rows as $row)fputcsv($f,$row);}rewind($f);$csv=stream_get_contents($f);fclose($f);
            return $this->response->setHeader('Content-Type','text/csv')->setHeader('Content-Disposition','attachment; filename="wage-estimates.csv"')->setBody($csv?:'');
        }
        return $this->render('attendance/report',['title'=>'Wage estimate','rows'=>$rows,'from'=>$from,'to'=>$to]);
    }
}
