<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

/** Costs are derived from recipe quantities in their declared units. */
class CostingService
{
    public function __construct(private readonly ?BaseConnection $connection = null) {}
    private function db(): BaseConnection {return $this->connection ?? db_connect();}

    /** Convert a recipe quantity into the ingredient purchasing unit. */
    public static function convert(float $quantity, string $from, string $to): float
    {
        $factors = ['g'=>1, 'kg'=>1000, 'ml'=>1, 'l'=>1000, 'pcs'=>1];
        if (! isset($factors[$from],$factors[$to]) || self::unitFamily($from) !== self::unitFamily($to)) {
            throw new \DomainException('Incompatible ingredient units.');
        }
        return $quantity * $factors[$from] / $factors[$to];
    }

    private static function unitFamily(string $unit): string
    {
        return match ($unit) {
            'g', 'kg' => 'mass',
            'ml', 'l' => 'volume',
            'pcs' => 'count',
            default => 'unknown',
        };
    }

    /** Convert a weighed or measured recipe line into an edible mass. */
    public static function nutrientQuantity(float $quantity, string $unit, float $gramsPerMl): float
    {
        if (!is_finite($gramsPerMl) || $gramsPerMl <= 0 || $gramsPerMl > 25) {
            throw new \DomainException('Liquid density must be greater than zero and at most 25 g/mL.');
        }
        return match ($unit) {
            'g' => $quantity,
            'kg' => $quantity * 1000,
            'ml' => $quantity * $gramsPerMl,
            'l' => $quantity * 1000 * $gramsPerMl,
            'pcs' => $quantity,
            default => throw new \DomainException('Unrecognized ingredient unit.'),
        };
    }

    public function calculate(int $productId): array
    {
        $db = $this->db();
        $product = $db->table('products')->where('id',$productId)->get()->getRowArray();
        if (! $product) throw new \DomainException('Product not found.');
        $rows = $db->table('product_recipes r')->select('r.*,i.name,i.purchase_unit,i.cost_per_unit,i.nutrient_basis,i.source_reference,i.density_g_per_ml,i.calories,i.protein_g,i.carbohydrates_g,i.fat_g,i.sugar_g,i.fiber_g,i.sodium_mg')
            ->join('ingredients i','i.id=r.ingredient_id')->where('r.product_id',$productId)->get()->getResultArray();
        $cost = 0.0;
        $nutrients = array_fill_keys(['calories','protein_g','carbohydrates_g','fat_g','sugar_g','fiber_g','sodium_mg'],0.0);
        $servings = max(1,(int) $product['recipe_servings']);
        $yield = max(0.001,(float) $product['cooking_yield']);
        foreach ($rows as $row) {
            $units = self::convert((float)$row['quantity'], (string)$row['unit'],(string)$row['purchase_unit']);
            $cost += $units * (float)$row['cost_per_unit'] * (1+(float)$row['wastage_percent']/100);
            $grams = self::nutrientQuantity((float)$row['quantity'], (string)$row['unit'], (float)$row['density_g_per_ml']);
            $basis = $row['nutrient_basis'] === 'unit' ? 1.0 : 100.0;
            foreach ($nutrients as $name => &$value) $value += $grams / $basis * (float)$row[$name];
            unset($value);
        }
        foreach ($nutrients as &$value) $value = round($value * $yield / $servings, 2);
        unset($value);
        $cost = $cost / $servings;
        $cost = ($cost + (float)$product['packaging_cost']) * (1+max(0,(float)$this->setting('cost_overhead_percent','0'))/100);
        $price = (float)$product['price'];
        $target = max(1,(float)$this->setting('target_food_cost_percent','35'));
        return [
            'unit_cost'=>round($cost,2),
            'gross_margin_percent'=>$price>0?round(($price-$cost)/$price*100,2):0,
            'food_cost_percent'=>$price>0?round($cost/$price*100,2):0,
            'suggested_price'=>round($cost / ($target/100),2),
            'nutrients'=>$nutrients,
            'ingredients'=>$rows,
        ];
    }
    private function setting(string $key,string $default): string
    {
        return (string)($this->db()->table('settings')->where('setting_key',$key)->get()->getRow('setting_value') ?? $default);
    }
    public function refreshNutrition(int $productId): void
    {
        $details=$this->calculate($productId);
        if ($details['ingredients'] === []) return;
        $nutrition=$details['nutrients'];
        $nutrition['calories']=(int)round($nutrition['calories']);
        $nutrition['sodium_mg']=(int)round($nutrition['sodium_mg']);
        $nutrition['nutrition_source']='computed';
        $nutrition['nutrition_status']='estimated';
        $nutrition['nutrition_verified_by']=null;
        $nutrition['nutrition_verified_at']=null;
        $this->db()->table('products')->where('id',$productId)->update($nutrition);
    }
}
