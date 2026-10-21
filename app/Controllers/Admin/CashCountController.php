<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\CashCountPolicy;
use App\Services\DailyInventoryService;
use App\Services\ReportService;
use App\Services\AuditLogService;
use Throwable;

class CashCountController extends BaseController
{
    private function admin(): bool { return (session()->get('user')['role']??'')==='admin'; }
    private function date(): string
    {
        $date=(string)($this->request->getGet('date')?:$this->request->getPost('date')?:date('Y-m-d'));
        DailyInventoryService::validateDate($date);return $date;
    }
    private function data(): array
    {
        $date=$this->date();$db=db_connect();
        if(!$this->admin() && $date!==date('Y-m-d'))throw new \DomainException('Cashiers may access today’s cash count only.');
        $row=$db->query('SELECT cc.*,u.name prepared_name,v.name checked_name FROM cash_counts cc LEFT JOIN users u ON u.id=cc.prepared_by LEFT JOIN users v ON v.id=cc.checked_by WHERE cc.business_date=?',[$date])->getRowArray();
        $count=$row?CashCountPolicy::count(json_decode((string)$row['denominations_json'],true)?:[]):CashCountPolicy::count([]);
        $credits=$row?(json_decode((string)$row['credit_entries_json'],true)?:[]):[];
        // Exactly the same DATE(created_at) and paid status predicate used in ReportService::sales().
        $expected=(float)(new ReportService($db))->sales($date,$date)['summary']['net_sales'];
        $byCashier=$db->query("SELECT COALESCE(u.name,'Online / unassigned') cashier,COUNT(*) orders,SUM(o.total) total FROM orders o LEFT JOIN users u ON u.id=o.cashier_id WHERE DATE(o.created_at)=? AND o.payment_status='paid' GROUP BY o.cashier_id,u.name ORDER BY cashier",[$date])->getResultArray();
        return compact('date','row','count','credits','expected','byCashier')+['variance'=>CashCountPolicy::variance($expected,$count['total']),'isAdmin'=>$this->admin()];
    }
    public function index(): string { return $this->render('admin/cash/count',$this->data()+['title'=>'Cash Count']); }
    public function printable(): string { return view('admin/cash/print',$this->data()); }
    public function save()
    {
        $date=$this->date();$db=db_connect();$db->transBegin();
        try {
            if(!$this->admin() && $date!==date('Y-m-d'))throw new \DomainException('Cashiers can prepare today’s count only.');
            $pieces=$this->request->getPost('pieces')??[];
            if(!is_array($pieces))throw new \DomainException('Invalid cash denominations.');
            $count=CashCountPolicy::count($pieces);
            $names=(array)($this->request->getPost('credit_name')??[]);
            $amounts=(array)($this->request->getPost('credit_amount')??[]);
            $notes=(array)($this->request->getPost('credit_remarks')??[]);
            $entries=[];
            foreach($names as $index=>$name) {
                if(trim((string)$name)==='' && trim((string)($amounts[$index]??''))==='')continue;
                $entries[]=['name'=>$name,'amount'=>$amounts[$index]??null,'remarks'=>$notes[$index]??''];
            }
            $credits=CashCountPolicy::credits($entries);
            $row=$db->query('SELECT * FROM cash_counts WHERE business_date=? FOR UPDATE',[$date])->getRowArray();
            if($row && $row['checked_at'])throw new \DomainException('This count is checked and locked. Ask an admin to reopen it with a reason.');
            if($row && !$this->admin() && $db->table('cash_count_revisions')->where('cash_count_id',$row['id'])->countAllResults()>0)
                throw new \DomainException('Only an admin may edit a previously checked count, following a recorded correction reason.');
            $now=date('Y-m-d H:i:s');$actor=(int)(session()->get('user')['id']??0);
            $values=['denominations_json'=>json_encode(array_map(static fn($line)=>$line['pieces'],$count['lines']),JSON_THROW_ON_ERROR),
                'credit_entries_json'=>json_encode($credits['entries'],JSON_THROW_ON_ERROR),'total_cash'=>$count['total'],'credit_total'=>$credits['total'],'updated_at'=>$now];
            if($row)$db->table('cash_counts')->where('id',$row['id'])->update($values);
            else $db->table('cash_counts')->insert($values+['business_date'=>$date,'prepared_by'=>$actor,'created_at'=>$now]);
            (new AuditLogService($db))->record($row?'cash_count_updated':'cash_count_created','cash_count',(int)($row['id']??$db->insertID()),['date'=>$date,'before_total'=>$row['total_cash']??null,'after_total'=>$count['total'],'before_credit_total'=>$row['credit_total']??null,'after_credit_total'=>$credits['total'],'credit_entries'=>count($credits['entries'])],$actor);
            if(!$db->transStatus())throw new \RuntimeException('Cash count could not be saved.');
            $db->transCommit();return redirect()->to($this->url($date))->with('success','Cash count saved.');
        }catch(Throwable $e){$db->transRollback();return redirect()->to($this->url($date))->with('error',$e->getMessage());}
    }
    public function approve()
    {
        if(!$this->admin())return redirect()->back()->with('error','Admin approval required.');
        $date=$this->date();$db=db_connect();$db->transBegin();
        try{
            $row=$db->query('SELECT * FROM cash_counts WHERE business_date=? FOR UPDATE',[$date])->getRowArray();
            if(!$row||$row['checked_at'])throw new \DomainException('The cash count must be prepared and not yet checked.');
            $actor=(int)(session()->get('user')['id']??0);
            $db->table('cash_counts')->where('id',$row['id'])->update(['checked_by'=>$actor,'checked_at'=>date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('cash_count_checked','cash_count',(int)$row['id'],['date'=>$date],$actor);
            if(!$db->transStatus())throw new \RuntimeException('Approval failed.');
            $db->transCommit();return redirect()->to($this->url($date))->with('success','Count checked and locked.');
        }catch(Throwable $e){$db->transRollback();return redirect()->to($this->url($date))->with('error',$e->getMessage());}
    }
    public function reopen()
    {
        if(!$this->admin())return redirect()->back()->with('error','Admin required.');
        $date=$this->date();$reason=trim((string)$this->request->getPost('reason'));
        if($reason===''||mb_strlen($reason)>1000)return redirect()->back()->with('error','An explanation (up to 1,000 characters) is required.');
        $db=db_connect();$db->transBegin();
        try{
            $row=$db->query('SELECT * FROM cash_counts WHERE business_date=? FOR UPDATE',[$date])->getRowArray();
            if(!$row||!$row['checked_at'])throw new \DomainException('Only checked counts can be reopened.');
            $actor=(int)(session()->get('user')['id']??0);
            $db->table('cash_count_revisions')->insert(['cash_count_id'=>$row['id'],'reopened_by'=>$actor,'reason'=>$reason,
                'prior_checked_by'=>$row['checked_by'],'prior_checked_at'=>$row['checked_at'],'created_at'=>date('Y-m-d H:i:s')]);
            $revisionId=(int)$db->insertID();
            $db->table('cash_counts')->where('id',$row['id'])->update(['checked_by'=>null,'checked_at'=>null]);
            (new AuditLogService($db))->record('cash_count_reopened','cash_count',(int)$row['id'],['date'=>$date,'revision_id'=>$revisionId,'previous_check'=>$row['checked_at'],'checked_by'=>$row['checked_by']]);
            if(!$db->transStatus())throw new \RuntimeException('Unlock failed.');
            $db->transCommit();return redirect()->to($this->url($date))->with('success','Count unlocked for correction.');
        }catch(Throwable $e){$db->transRollback();return redirect()->to($this->url($date))->with('error',$e->getMessage());}
    }
    private function url(string $date): string { return '/'.($this->admin()?'admin':'cashier').'/cash-count?date='.$date; }
}
