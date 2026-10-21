<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DailyInventoryService
{
    public function __construct(private readonly ?BaseConnection $connection=null) {}

    public function report(string $date): array
    {
        self::validateDate($date);
        $db=$this->connection??db_connect();
        $start=$date.' 00:00:00';
        $end=(new \DateTimeImmutable($date,new \DateTimeZone('Asia/Manila')))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        $products=$db->query("SELECT p.*,c.name category_name,c.sort_order category_order FROM products p JOIN categories c ON c.id=p.category_id WHERE p.deleted_at IS NULL AND (p.created_at IS NULL OR p.created_at < ?) ORDER BY c.sort_order,c.name,p.id",[$end])->getResultArray();
        $beginnings=[];
        foreach($db->query('SELECT product_id,SUM(quantity) balance FROM inventory_movements WHERE created_at < ? GROUP BY product_id',[$start])->getResultArray() as $r) $beginnings[(int)$r['product_id']]=(int)$r['balance'];
        $movements=[];
        $latestCounts=[];
        foreach($db->query('SELECT id,product_id,quantity,stock_after,movement_type,reference,reason_code,note,created_at FROM inventory_movements WHERE created_at>=? AND created_at<? ORDER BY created_at,id',[$start,$end])->getResultArray() as $r) {
            $id=(int)$r['product_id'];$movements[$id][]=$r;
            if($r['movement_type']==='adjustment' && $r['reference']==='PHYSICAL-COUNT') $latestCounts[$id]=$r;
        }
        $costing=new CostingService($db);
        $groups=[];$totals=['sold'=>0,'pos_sold'=>0,'variance'=>0,'amount'=>0.0,'total_cost'=>0.0,'profit'=>0.0,'uncosted_revenue'=>0.0];
        foreach($products as $product) {
            $id=(int)$product['id'];$begin=$beginnings[$id]??0;$additional=0;$withdrawn=0;$pos=0;$balance=$begin;$remarks=[];
            foreach($movements[$id]??[] as $movement) {
                $qty=(int)$movement['quantity'];$type=$movement['movement_type'];$balance+=$qty;
                if($type==='stock_in')$additional+=max(0,$qty);
                if(in_array($type,['waste','stock_out'],true)) {
                    $withdrawn+=abs($qty);
                    $text=trim(($movement['reason_code']?:'Legacy withdrawal').': '.($movement['note']??''));
                    $remarks[]=$text;
                }
                if($type==='sale')$pos+=abs($qty);
                if($type==='cancel_restock')$pos-=abs($qty);
                if($type==='adjustment' && $movement['reference']!=='PHYSICAL-COUNT')$remarks[]='Adjustment: '.($movement['note']??'');
            }
            $counted=isset($latestCounts[$id]);
            // A count is a reconciliation movement; subsequent ledger entries remain visible in the end balance.
            $costData=$costing->calculate($id);
            $unitCost=$costData['cost_status']==='complete'?(float)$costData['unit_cost']:
                ($product['standard_unit_cost']!==null?(float)$product['standard_unit_cost']:null);
            $row=DailyInventoryPolicy::calculate($begin,$additional,$withdrawn,$balance,$pos,$product,$unitCost);
            $row+=['product_id'=>$id,'name'=>$product['name'],'unit'=>$product['stock_unit'],'category'=>$product['category_name'],
                'ending_source'=>$counted?'counted':'system','price_label'=>BundlePricePolicy::label($product),
                'remarks'=>implode('; ',$remarks),'cost_status'=>$unitCost===null?'uncosted':($costData['cost_status']==='complete'?'recipe':'standard')];
            $group=$product['category_name'];
            if(!isset($groups[$group]))$groups[$group]=['rows'=>[],'totals'=>['sold'=>0,'pos_sold'=>0,'variance'=>0,'amount'=>0.0,'total_cost'=>0.0,'profit'=>0.0,'uncosted_revenue'=>0.0]];
            $groups[$group]['rows'][]=$row;
            foreach(['sold','pos_sold','variance'] as $field) { $groups[$group]['totals'][$field]+=$row[$field];$totals[$field]+=$row[$field]; }
            foreach(['amount','total_cost','profit'] as $field) if($row[$field]!==null) { $groups[$group]['totals'][$field]+=$row[$field];$totals[$field]+=$row[$field]; }
            if($unitCost===null && $row['amount']!==null) { $groups[$group]['totals']['uncosted_revenue']+=$row['amount'];$totals['uncosted_revenue']+=$row['amount']; }
        }
        $cash=$db->table('cash_counts')->select('credit_total')->where('business_date',$date)->get()->getRowArray();
        $check=$db->query('SELECT d.*,p.name prepared_name,c.name checked_name FROM daily_inventory_checks d LEFT JOIN users p ON p.id=d.prepared_by LEFT JOIN users c ON c.id=d.checked_by WHERE d.business_date=?',[$date])->getRowArray();
        $changedSinceCheck=false;
        if($check && $check['checked_at']) {
            $changedSinceCheck=(int)($db->query('SELECT COUNT(*) n FROM inventory_movements WHERE created_at>=? AND created_at<? AND id>?',[$start,$end,(int)$check['checked_movement_id']])->getRow('n')??0)>0;
        }
        return ['date'=>$date,'day'=>date('l / F d, Y',strtotime($date)),'groups'=>$groups,'totals'=>$totals,'credit_total'=>(float)($cash['credit_total']??0),
            'check'=>$check,'changed_since_check'=>$changedSinceCheck];
    }
    public static function validateDate(string $date): void
    {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Asia/Manila'));
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \DomainException('Invalid business date.');
    }
}
