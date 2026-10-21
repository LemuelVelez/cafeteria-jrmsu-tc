<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\DailyInventoryService;
use App\Services\AuditLogService;
use Throwable;

class DailyInventoryController extends BaseController
{
    private function date(): string
    {
        $date=(string)($this->request->getGet('date')?:$this->request->getPost('date')?:date('Y-m-d'));
        DailyInventoryService::validateDate($date);
        return $date;
    }
    public function printable(): string
    {
        return view('admin/reports/daily_inventory_print',['report'=>(new DailyInventoryService())->report($this->date())]);
    }
    public function export()
    {
        $report=(new DailyInventoryService())->report($this->date());
        $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");
        $put=static fn(array $row)=>fputcsv($stream,array_map([ReportController::class,'safeCsvCell'],$row),',','"','');
        $put(['Jose Rizal Memorial State University – Tampilisan Campus','DAILY INVENTORY OF COUNTER ITEMS DISPLAY AND SALES REPORT']);
        $put(['Day/Date',$report['day']]);
        $put(['Items','Beginning Inventory','Additional','Total Stock','Withdrawn','Ending Inventory','Source','Number of Items Sold','POS sold','Variance','Cost of Item Sold','Total Cost','Selling Price','Total Amount of Items Sold','Net Profit','Remarks']);
        foreach($report['groups'] as $category=>$group) {
            $put([$category]);
            foreach($group['rows'] as $r)$put([$r['name'].' ('.$r['unit'].')',$r['beginning'],$r['additional'],$r['total_stock'],$r['withdrawn'],$r['ending'],$r['ending_source'],$r['sold'],$r['pos_sold'],$r['variance'],$r['unit_cost']??'—',$r['total_cost']??'—',$r['price_label'],$r['amount']??'—',$r['profit']??'—',$r['remarks']]);
            $put([$category.' SUBTOTAL','','','','','','',$group['totals']['sold'],$group['totals']['pos_sold'],$group['totals']['variance'],'',$group['totals']['total_cost'],'',$group['totals']['amount'],$group['totals']['profit']]);
        }
        $put(['Credit Payment','','','','','','','','','','','','',$report['credit_total']]);
        $put(['GRAND TOTAL','','','','','','',$report['totals']['sold'],$report['totals']['pos_sold'],$report['totals']['variance'],'',$report['totals']['total_cost'],'',$report['totals']['amount'],$report['totals']['profit']]);
        $put(['Uncosted revenue',$report['totals']['uncosted_revenue']]);
        $check=$report['check'];$put(['Prepared by',$check['prepared_name']??'Pending',$check['prepared_at']??'']);$put(['Checked by',$check['checked_name']??'Pending',$check['checked_at']??'']);
        rewind($stream);$content=stream_get_contents($stream);fclose($stream);
        return $this->response->setHeader('Content-Type','text/csv; charset=UTF-8')->setHeader('Content-Disposition','attachment; filename="daily-inventory-'.$report['date'].'.csv"')->setBody($content?:'');
    }
    public function action()
    {
        $date=$this->date();$operation=(string)$this->request->getPost('operation');
        $db=db_connect();$db->transBegin();
        try {
            $row=$db->query('SELECT * FROM daily_inventory_checks WHERE business_date=? FOR UPDATE',[$date])->getRowArray();
            $actor=(int)(session()->get('user')['id']??0);$now=date('Y-m-d H:i:s');
            if($operation==='prepare' && !$row) {
                $db->table('daily_inventory_checks')->insert(['business_date'=>$date,'prepared_by'=>$actor,'prepared_at'=>$now]);
            } elseif($operation==='check' && $row && !$row['checked_at']) {
                $nextDay=(new \DateTimeImmutable($date,new \DateTimeZone('Asia/Manila')))->modify('+1 day')->format('Y-m-d').' 00:00:00';
                $lastId=(int)($db->query('SELECT COALESCE(MAX(id),0) movement_id FROM inventory_movements WHERE created_at < ?',[$nextDay])->getRow('movement_id')??0);
                $db->table('daily_inventory_checks')->where('id',$row['id'])->update(['checked_by'=>$actor,'checked_at'=>$now,'checked_movement_id'=>$lastId]);
            } else throw new \DomainException('Report already locked, or prepare the report before checking.');
            (new AuditLogService($db))->record('daily_inventory_'.$operation,'daily_inventory',(int)($row['id']??$db->insertID()),['date'=>$date],$actor);
            if(!$db->transStatus())throw new \RuntimeException('Unable to save sign-off.');
            $db->transCommit();return redirect()->to('/admin/reports?type=daily&from='.$date.'&to='.$date)->with('success','Daily inventory '.$operation.' recorded.');
        }catch(Throwable $e){$db->transRollback();return redirect()->back()->with('error',$e->getMessage());}
    }
}
