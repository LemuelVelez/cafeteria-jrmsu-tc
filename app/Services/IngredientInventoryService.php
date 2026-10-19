<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** All ingredient stock changes occur through this ledger, inside caller transactions. */
final class IngredientInventoryService
{
    public function __construct(private readonly ?BaseConnection $connection=null) {}
    private function db(): BaseConnection {return $this->connection ?? db_connect();}

    public function enabled(): bool
    {
        return (string)($this->db()->table('settings')->where('setting_key','inventory_ingredient_tracking_enabled')->get()->getRow('setting_value') ?? '0') === '1';
    }

    /** Recipe quantities are batch quantities. Units are converted into each ingredient's purchasing unit. */
    public function requirements(array $productQuantities): array
    {
        if (!$productQuantities)return [];
        $rows=$this->db()->table('product_recipes r')->select('r.product_id,r.ingredient_id,r.quantity,r.unit,r.wastage_percent,p.recipe_servings,i.purchase_unit,i.density_g_per_ml,i.grams_per_piece')
            ->join('products p','p.id=r.product_id')->join('ingredients i','i.id=r.ingredient_id')
            ->whereIn('r.product_id',array_keys($productQuantities))->get()->getResultArray();
        $needed=[];
        foreach ($rows as $row) {
            $quantity=(int)($productQuantities[(int)$row['product_id']] ?? 0);
            if ($quantity<1)continue;
            $used=CostingService::ingredientUnits((float)$row['quantity'],(string)$row['unit'],
                (string)$row['purchase_unit'],(float)$row['density_g_per_ml'],
                $row['grams_per_piece']===null?null:(float)$row['grams_per_piece']);
            $used *= $quantity / max(1,(int)$row['recipe_servings']);
            $used *= 1 + (float)$row['wastage_percent']/100;
            $id=(int)$row['ingredient_id'];
            $needed[$id]=($needed[$id]??0)+$used;
        }
        ksort($needed,SORT_NUMERIC); // deterministic locking order
        return array_map(static fn(float $v):float => round($v,3),$needed);
    }

    public function saleForOrder(int $orderId,array $products,?int $actorId): void
    {
        if (!$this->enabled())return;
        foreach ($this->requirements($products) as $id=>$quantity) {
            if ($quantity>0)$this->change((int)$id,-$quantity,'sale',$orderId,$actorId,'Recipe reserved for order');
        }
    }

    public function restockForCancellation(int $orderId,?int $actorId): void
    {
        // Use actual sale movements, not current recipes; recipes can change after purchase.
        $rows=$this->db()->table('ingredient_movements')->where('order_id',$orderId)->where('movement_type','sale')->orderBy('ingredient_id')->get()->getResultArray();
        foreach($rows as $row) {
            $id=(int)$row['ingredient_id'];
            $already=$this->db()->table('ingredient_movements')->where(['order_id'=>$orderId,'ingredient_id'=>$id,'movement_type'=>'cancel_restock'])->countAllResults();
            if ($already)continue;
            $this->change($id,abs((float)$row['quantity']),'cancel_restock',$orderId,$actorId,'Order cancelled');
        }
    }

    public function stockIn(int $ingredientId,float $quantity,?int $actorId,string $note): void
    {
        if ($quantity<=0 || $quantity>10000000 || trim($note)==='')throw new \DomainException('Positive quantity and a note are required.');
        $this->change($ingredientId,$quantity,'stock_in',null,$actorId,$note);
    }

    private function change(int $ingredientId,float $delta,string $type,?int $orderId,?int $actorId,string $note): void
    {
        $db=$this->db();
        $ingredient=$db->query('SELECT * FROM ingredients WHERE id=? FOR UPDATE',[$ingredientId])->getRowArray();
        if (!$ingredient)throw new \DomainException('Ingredient not found.');
        $before=(float)$ingredient['stock']; $after=round($before+$delta,3);
        if($after<0)throw new \DomainException('Insufficient ingredient stock: '.$ingredient['name']);
        if (!$db->table('ingredients')->where('id',$ingredientId)->update(['stock'=>$after]))throw new \RuntimeException('Unable to update ingredient stock.');
        if (!$db->table('ingredient_movements')->insert(['ingredient_id'=>$ingredientId,'movement_type'=>$type,'quantity'=>round($delta,3),'stock_before'=>$before,'stock_after'=>$after,'order_id'=>$orderId,'user_id'=>$actorId,'note'=>mb_substr($note,0,255),'created_at'=>date('Y-m-d H:i:s')]))throw new \RuntimeException('Unable to record ingredient movement.');
        (new AuditLogService($db))->record('ingredient_inventory_movement','ingredient',$ingredientId,['type'=>$type,'delta'=>$delta,'before'=>$before,'after'=>$after],$actorId);
    }
}
