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
    public static function nutrientQuantity(float $quantity, string $unit, float $gramsPerMl, ?float $gramsPerPiece = null): float
    {
        if (!is_finite($gramsPerMl) || $gramsPerMl <= 0 || $gramsPerMl > 25) {
            throw new \DomainException('Liquid density must be greater than zero and at most 25 g/mL.');
        }
        return match ($unit) {
            'g' => $quantity,
            'kg' => $quantity * 1000,
            'ml' => $quantity * $gramsPerMl,
            'l' => $quantity * 1000 * $gramsPerMl,
            'pcs' => ($gramsPerPiece !== null && $gramsPerPiece > 0)
                ? $quantity * $gramsPerPiece
                : throw new \DomainException('Grams per piece are required for 100g nutrition of an ingredient measured in pcs.'),
            default => throw new \DomainException('Unrecognized ingredient unit.'),
        };
    }

    /** Purchase and recipe unit families can differ only with measured conversion data. */
    public static function ingredientUnits(float $quantity, string $from, string $to, float $density, ?float $gramsPerPiece): float
    {
        if (self::unitFamily($from) === self::unitFamily($to)) return self::convert($quantity, $from, $to);
        $mass = match ($from) {
            'g' => $quantity, 'kg' => $quantity * 1000,
            'ml' => $quantity * $density, 'l' => $quantity * 1000 * $density,
            'pcs' => $gramsPerPiece !== null && $gramsPerPiece > 0 ? $quantity * $gramsPerPiece
                : throw new \DomainException('Grams per piece required for mixed-unit recipe.'),
            default => throw new \DomainException('Unknown recipe unit.'),
        };
        if ($density <= 0 || $density > 25) throw new \DomainException('Liquid density must be positive.');
        return match ($to) {
            'g' => $mass, 'kg' => $mass / 1000,
            'ml' => $mass / $density, 'l' => $mass / (1000 * $density),
            'pcs' => $gramsPerPiece !== null && $gramsPerPiece > 0 ? $mass / $gramsPerPiece
                : throw new \DomainException('Grams per piece required for mixed-unit recipe.'),
            default => throw new \DomainException('Unknown purchase unit.'),
        };
    }

    /** Water loss changes mass, not the nutrient total in the prepared recipe. */
    public static function perServingNutrients(array $batch, int $servings): array
    {
        if ($servings < 1) throw new \DomainException('Recipe must have at least one serving.');
        return array_map(static fn ($value): float => round((float) $value / $servings, 2), $batch);
    }

    public function calculate(int $productId): array
    {
        $db = $this->db();
        $product = $db->table('products')->where('id',$productId)->get()->getRowArray();
        if (! $product) throw new \DomainException('Product not found.');
        $rows = $db->table('product_recipes r')->select('r.*,i.name,i.purchase_unit,i.cost_per_unit,i.nutrient_basis,i.source_reference,i.density_g_per_ml,i.grams_per_piece,i.calories,i.protein_g,i.carbohydrates_g,i.fat_g,i.sugar_g,i.fiber_g,i.sodium_mg')
            ->join('ingredients i','i.id=r.ingredient_id')->where('r.product_id',$productId)->get()->getResultArray();
        $cost = 0.0;
        $nutrients = array_fill_keys(['calories','protein_g','carbohydrates_g','fat_g','sugar_g','fiber_g','sodium_mg'],0.0);
        $servings = max(1,(int) $product['recipe_servings']);

        foreach ($rows as $row) {
            $gramsPerPiece = $row['grams_per_piece'] === null ? null : (float) $row['grams_per_piece'];
            $units = self::ingredientUnits((float)$row['quantity'], (string)$row['unit'],
                (string)$row['purchase_unit'], (float)$row['density_g_per_ml'], $gramsPerPiece);
            $cost += $units * (float)$row['cost_per_unit'] * (1+(float)$row['wastage_percent']/100);
            $grams = $row['nutrient_basis'] === 'unit'
                ? $units // Nutrients per purchased unit, irrespective of recipe line unit.
                : self::nutrientQuantity((float)$row['quantity'], (string)$row['unit'],
                    (float)$row['density_g_per_ml'], $gramsPerPiece);
            $basis = $row['nutrient_basis'] === 'unit' ? 1.0 : 100.0;
            foreach ($nutrients as $name => &$value) $value += $grams / $basis * (float)$row[$name];
            unset($value);
        }
        // Cooking yield affects grams of cooked food and per-100g figures, not per-serving nutrients.
        $nutrients = self::perServingNutrients($nutrients, $servings);
        $cost = $cost / $servings;
        $cost = ($cost + (float)$product['packaging_cost']) * (1+max(0,(float)$this->setting('cost_overhead_percent','0'))/100);
        $price = (float)$product['price'];
        $target = max(1,(float)$this->setting('target_food_cost_percent','35'));
        return [
            'cost_status'=>$rows === [] ? 'no_recipe' : 'complete',
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
        $sources = array_unique(array_filter(array_map(static fn (array $row): string =>
            trim((string) ($row['source_reference'] ?? '')), $details['ingredients'])));
        $nutrition['nutrition_source'] = count($sources) === count($details['ingredients'])
            ? 'recipe_sourced' : 'recipe_unverified';
        $nutrition['nutrition_status']='estimated';
        $nutrition['nutrition_verified_by']=null;
        $nutrition['nutrition_verified_at']=null;
        $this->db()->table('products')->where('id',$productId)->update($nutrition);
    }
}
