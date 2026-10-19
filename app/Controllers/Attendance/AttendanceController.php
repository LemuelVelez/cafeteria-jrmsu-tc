<?php
namespace App\Controllers\Attendance;
use App\Controllers\BaseController;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use App\Services\AttendanceInputPolicy;
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
            // Lock the employee record even on their very first clock-in, when no punch row exists.
            $employee=$db->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$employee)throw new \DomainException('Employee not found.');
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
            'hourly_rate'=>$this->request->getPost('hourly_rate'),'effective_from'=>date('Y-m-d')]);
    }
    private function saveAdmin(string $table,array $values)
    {
        if (!$this->admin())return redirect()->to('/attendance');
        $db=db_connect();$db->transBegin();
        try {
            // Lock staff to serialize concurrent shift/rate edits for the same employee.
            $staff = $db->query('SELECT id, role, status FROM users WHERE id=? FOR UPDATE',
                [$values['user_id']])->getRowArray();
            if ($values['user_id'] < 1 || ! $staff || ($staff['status'] ?? '') !== 'active'
                || ! in_array($staff['role'], ['admin', 'cashier', 'rider'], true)) {
                throw new \DomainException('Choose an active staff employee.');
            }
            $audit = new AuditLogService($db);
            if ($table === 'attendance_shifts') {
                AttendanceInputPolicy::validateShift((int) $values['weekday'],
                    $values['start_time'], $values['end_time'], (int) $values['grace_minutes']);
                $existing = $db->table($table)->where(['user_id' => $values['user_id'],
                    'weekday' => $values['weekday']])->get()->getRowArray();
                if ($existing) {
                    $db->table($table)->where('id', $existing['id'])->update($values);
                    $audit->recordChange('attendance_shift_changed', 'attendance_shift', (int) $existing['id'],
                        array_intersect_key($existing, $values), $values, (int) session()->get('user')['id']);
                } else {
                    $db->table($table)->insert($values);
                    $audit->record('attendance_shift_created', 'attendance_shift', (int) $db->insertID(),
                        ['weekday' => $values['weekday'], 'user_id' => $values['user_id']], (int) session()->get('user')['id']);
                }
            } else {
                $values['hourly_rate'] = AttendanceInputPolicy::hourlyRate($values['hourly_rate']);
                $existing = $db->table($table)->where(['user_id' => $values['user_id'],
                    'effective_from' => $values['effective_from']])->orderBy('id', 'DESC')->get()->getRowArray();
                if ($existing) {
                    $db->table($table)->where('id', $existing['id'])->update($values);
                    $audit->recordChange('attendance_rate_changed', 'attendance_pay_rate', (int) $existing['id'],
                        ['hourly_rate' => (float) $existing['hourly_rate']],
                        ['hourly_rate' => (float) $values['hourly_rate']], (int) session()->get('user')['id']);
                } else {
                    $db->table($table)->insert($values);
                    $audit->record('attendance_rate_created', 'attendance_pay_rate', (int) $db->insertID(),
                        ['user_id' => $values['user_id'], 'effective_from' => $values['effective_from']],
                        (int) session()->get('user')['id']);
                }
            }
            if(!$db->transStatus())throw new \RuntimeException('Unable to save payroll settings.');
            $db->transCommit();return redirect()->to('/admin/attendance')->with('success','Attendance settings saved.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/attendance')->with('error',$e->getMessage());}
    }
    public function correct(int $id)
    {
        if(!$this->admin())return redirect()->to('/attendance');
        $new=(string)$this->request->getPost('at');$reason=trim((string)$this->request->getPost('reason'));
        if ($reason === '') return redirect()->to('/admin/attendance')->with('error','Correction reason required.');
        try { $correctedAt = AttendanceInputPolicy::correctedAt($new); }
        catch (\DomainException $error) { return redirect()->to('/admin/attendance')->with('error',$error->getMessage()); }
        $db=db_connect();$db->transBegin();
        try{
            $old=$db->query('SELECT * FROM attendance_punches WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$old)throw new \DomainException('Punch not found.');
            $values=['at'=>$correctedAt,'edited_by'=>session()->get('user')['id'],'edit_reason'=>$reason];
            $db->table('attendance_punches')->where('id',$id)->update($values);
            (new AuditLogService($db))->recordChange('attendance_correction','attendance_punch',$id,['at'=>$old['at']],['at'=>$values['at']]);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to save correction.');
            $db->transCommit();return redirect()->to('/admin/attendance')->with('success','Corrected.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/attendance')->with('error',$e->getMessage());}
    }
    public function report()
    {
        if(!$this->admin())return redirect()->to('/attendance');
        $from=(string)($this->request->getGet('from')?:date('Y-m-01'));$to=(string)($this->request->getGet('to')?:date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to)return redirect()->to('/admin/attendance')->with('error','Invalid date range.');
        $rows=(new \App\Services\ReportService())->attendance($from,$to);
        if($this->request->getGet('format')==='csv'){
            $f=fopen('php://temp','w+');fwrite($f,"\xEF\xBB\xBF");
            $csvRow = static function(array $cells) use ($f): void {
                fputcsv($f,array_map([\App\Controllers\Admin\ReportController::class,'safeCsvCell'],$cells),',','"','');
            };
            $csvRow(['Report','Attendance wage estimates']);
            $csvRow(['Date range',$from.' to '.$to]);
            $csvRow(['Generated at',date('Y-m-d H:i:s')]);
            $csvRow([]);
            if($rows){$csvRow(array_keys($rows[0]));foreach($rows as $row)$csvRow(array_values($row));}
            rewind($f);$csv=stream_get_contents($f);fclose($f);
            return $this->response->setHeader('Content-Type','text/csv; charset=UTF-8')->setHeader('Content-Disposition','attachment; filename="wage-estimates.csv"')->setBody($csv?:'');
        }
        return $this->render('attendance/report',['title'=>'Wage estimate','rows'=>$rows,'from'=>$from,'to'=>$to]);
    }
}
