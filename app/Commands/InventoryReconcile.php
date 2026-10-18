<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InventoryReconcile extends BaseCommand
{
    protected $group = 'Inventory';
    protected $name = 'inventory:reconcile';
    protected $description = 'Verify product stock against the inventory movement ledger and all open-order reservations.';

    public function run(array $params): void
    {
        $db = db_connect();
        $products = $db->table('products')->select('id,name,stock')->where('deleted_at', null)->get()->getResultArray();
        $mismatches = 0;
        $details = [];

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $ledgerStock = (int) ($db->query('SELECT COALESCE(SUM(quantity),0) qty FROM inventory_movements WHERE product_id=?', [$productId])->getRow('qty') ?? 0);
            if ($ledgerStock !== (int) $product['stock']) {
                CLI::error(sprintf('%s: stock=%d ledger=%d', $product['name'], $product['stock'], $ledgerStock));
                $mismatches++;
                $details[] = 'Product '.$productId.' stock '.$product['stock'].' differs from ledger '.$ledgerStock;
            }

            $reservedSold = (int) ($db->query(
                "SELECT COALESCE(SUM(oi.quantity),0) qty FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=? AND o.status <> 'cancelled'",
                [$productId],
            )->getRow('qty') ?? 0);
            $ledgerNetSold = abs((int) ($db->query(
                "SELECT COALESCE(SUM(CASE WHEN movement_type='sale' THEN quantity WHEN movement_type='cancel_restock' THEN quantity ELSE 0 END),0) qty FROM inventory_movements WHERE product_id=?",
                [$productId],
            )->getRow('qty') ?? 0));
            if ($reservedSold !== $ledgerNetSold) {
                CLI::error(sprintf('%s: reserved units=%d ledger net sale=%d', $product['name'], $reservedSold, $ledgerNetSold));
                $mismatches++;
                $details[] = 'Product '.$productId.' reserved units '.$reservedSold.' differ from net sales '.$ledgerNetSold;
            }
        }

        // Check ingredient balances against their own transaction ledger.
        if ($db->tableExists('ingredient_movements')) {
            foreach($db->query('SELECT i.id,i.name,i.stock,COALESCE(SUM(m.quantity),0) ledger FROM ingredients i LEFT JOIN ingredient_movements m ON i.id=m.ingredient_id GROUP BY i.id,i.name,i.stock')->getResultArray() as $ingredient) {
                if(abs((float)$ingredient['stock']-(float)$ingredient['ledger'])>0.001){
                    $mismatches++;
                    $details[]='Ingredient '.(int)$ingredient['id'].' stock mismatch';
                    CLI::error('Ingredient '.$ingredient['name'].' does not match its ledger.');
                }
            }
        }
        // Only send a new alert when the mismatch signature changes, avoiding scheduled-job spam.
        $hash=$details?hash('sha256',implode("\n",$details)):'';
        $before=(string)($db->table('settings')->where('setting_key','inventory_reconcile_hash')->get()->getRow('setting_value')??'');
        if ($hash!==$before) {
            $db->transBegin();
            try {
                if($hash!==''){
                    foreach($db->table('users')->select('id')->where('role','admin')->where('status','active')->get()->getResultArray() as $staff){
                        $db->table('notifications')->insert(['user_id'=>$staff['id'],'order_id'=>null,'type'=>'inventory_discrepancy','title'=>'Inventory discrepancy detected','message'=>$mismatches.' stock inconsistencies were found. Run inventory:reconcile.','link'=>'/admin/inventory','read_at'=>null,'created_at'=>date('Y-m-d H:i:s')]);
                    }
                }
                (new \App\Models\SettingModel())->setValue('inventory_reconcile_hash',$hash);
                if (!$db->transStatus())throw new \RuntimeException('Reconcile status update failed.');
                $db->transCommit();
            }catch(\Throwable $e){$db->transRollback();throw $e;}
        }
        if ($mismatches === 0) {
            CLI::write('Inventory reconciliation passed: zero mismatches.', 'green');
            return;
        }
        CLI::error('Inventory reconciliation found ' . $mismatches . ' mismatch(es).');
    }
}
