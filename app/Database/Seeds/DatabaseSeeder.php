<?php

namespace App\Database\Seeds;

use App\Services\NutritionCatalogAudit;
use App\Services\DemoProductNutritionCatalog;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Single source of truth for application seed order.
     *
     * @var list<string>
     */
    public const SEEDERS = [
        'UserSeeder',
        'CategorySeeder',
        'ProductSeeder',
        // Independent, approved product recipes must run BEFORE legacy catalog seeders.
        'ChickenAdoboRiceSeeder',
        'PorkSisigRiceSeeder',
        'FriedChickenMealSeeder',
        'CheeseBurgerSeeder',
        'CrispyFriesSeeder',
        'IcedSpanishLatteSeeder',
        'HotAmericanoSeeder',
        'CalamansiJuiceSeeder',
        'IngredientSeeder',
        'RecipeSeeder',
        'SettingSeeder',
        'OperationalSettingsSeeder',
        'PromoSeeder',
        'NotificationSeeder',
    ];

    /**
     * @return list<string>
     */
    public static function seeders(): array
    {
        return self::SEEDERS;
    }

    /**
     * Detects seeders that were already applied before seeder_runs tracking
     * existed. This lets upgraded databases adopt current seeders as existing
     * instead of executing them again.
     */
    public static function isSatisfied(string $seeder, BaseConnection $db): bool
    {
        return match ($seeder) {
            'UserSeeder' => self::hasAllValues($db, 'users', 'email', [
                'admin@jrmsu.edu.ph',
                'cashier@jrmsu.edu.ph',
                'rider@jrmsu.edu.ph',
                'customer@jrmsu.edu.ph',
            ]),
            'CategorySeeder' => self::hasAllValues($db, 'categories', 'slug', [
                'rice-meals',
                'snacks',
                'coffee',
                'cold-drinks',
            ]),
            'ProductSeeder' => self::productSeedIsSatisfied($db),
            'ChickenAdoboRiceSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'chicken-adobo-rice'),
            'PorkSisigRiceSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'pork-sisig-rice'),
            'FriedChickenMealSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'fried-chicken-meal'),
            'CheeseBurgerSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'cheese-burger'),
            'CrispyFriesSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'crispy-fries'),
            'IcedSpanishLatteSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'iced-spanish-latte'),
            'HotAmericanoSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'hot-americano'),
            'CalamansiJuiceSeeder' => DemoProductNutritionCatalog::isSatisfied($db, 'calamansi-juice'),
            'IngredientSeeder' => self::ingredientSeedIsSatisfied($db),
            'RecipeSeeder' => self::recipeSeedIsSatisfied($db),
            'SettingSeeder' => self::hasAllValues($db, 'settings', 'setting_key', [
                'cafeteria_name',
                'delivery_fee',
                'operating_hours',
                'contact_number',
                'pickup_enabled',
                'delivery_enabled',
                'email_order_notifications',
            ]),
            'OperationalSettingsSeeder' => self::hasAllValues($db, 'settings', 'setting_key', array_keys(OperationalSettingsSeeder::DEFAULTS)),
            'PromoSeeder' => self::hasAllValues($db, 'promos', 'code', ['WELCOME10']),
            'NotificationSeeder' => self::notificationSeedIsSatisfied($db),
            default => false,
        };
    }

    public function run(): void
    {
        // The test framework can call this directly. Apply each product only
        // after its kitchen worksheet is approved. Legacy bulk seeders stay
        // disabled: the eight custom seeders cover the same data and avoid
        // double-importing recipes or overwriting existing administrator edits.
        $ingredients = IngredientSeeder::rows();
        $recipes = RecipeSeeder::rows();
        $worksheet = DemoProductNutritionCatalog::worksheet();
        foreach (self::SEEDERS as $seeder) {
            if (in_array($seeder, ['IngredientSeeder', 'RecipeSeeder'], true)) continue;
            $slug = DemoProductNutritionCatalog::slugForSeeder($seeder);
            if ($slug !== null && ! DemoProductNutritionCatalog::assess($slug, $ingredients, $recipes, $worksheet)['ready']) {
                continue;
            }
            $this->call($seeder);
        }
    }

    /**
     * @param list<string> $values
     */
    private static function hasAllValues(BaseConnection $db, string $table, string $column, array $values): bool
    {
        if (! $db->tableExists($table) || $values === []) {
            return false;
        }

        $count = $db->table($table)
            ->whereIn($column, $values)
            ->countAllResults();

        return $count === count($values);
    }

    private static function productSeedIsSatisfied(BaseConnection $db): bool
    {
        $slugs = [
            'chicken-adobo-rice',
            'pork-sisig-rice',
            'fried-chicken-meal',
            'cheese-burger',
            'crispy-fries',
            'iced-spanish-latte',
            'hot-americano',
            'calamansi-juice',
        ];

        if (! self::hasAllValues($db, 'products', 'slug', $slugs)
            || ! $db->tableExists('product_addons')
            || ! $db->tableExists('inventory_movements')) {
            return false;
        }

        $addonNames = ['Extra Rice', 'Extra Egg', 'Extra Espresso Shot', 'Cheese Dip'];
        $addonCount = $db->table('product_addons')
            ->whereIn('name', $addonNames)
            ->countAllResults();
        if ($addonCount < count($addonNames)) {
            return false;
        }

        $productIds = array_column(
            $db->table('products')->select('id')->whereIn('slug', $slugs)->get()->getResultArray(),
            'id',
        );
        if (count($productIds) !== count($slugs)) {
            return false;
        }

        // Do not combine Query Builder's countAllResults() with GROUP BY here.
        // MySQL's ONLY_FULL_GROUP_BY mode can cause CodeIgniter to emit a grouped
        // count query that still selects a non-grouped primary key. Counting the
        // distinct seeded product IDs is equivalent and is safe in strict mode.
        $openingRow = $db->table('inventory_movements')
            ->select('COUNT(DISTINCT product_id) AS seeded_products', false)
            ->whereIn('product_id', $productIds)
            ->where('movement_type', 'opening')
            ->get()
            ->getRowArray();
        $openingCount = (int) ($openingRow['seeded_products'] ?? 0);

        return $openingCount === count($productIds);
    }

    private static function ingredientSeedIsSatisfied(BaseConnection $db): bool
    {
        if (! NutritionCatalogAudit::assess(IngredientSeeder::rows(), RecipeSeeder::rows())['ready']) return false;
        if (! $db->tableExists('ingredients') || ! $db->tableExists('ingredient_movements')) return false;
        $rows = IngredientSeeder::rows();
        // An empty source catalog is not a successfully applied seeder. The team
        // must supply real nutrient references before this can be satisfied.
        if ($rows === []) return false;
        foreach ($rows as $ingredient) {
            $record = $db->table('ingredients')->select('id')->where('name', $ingredient['name'])->get()->getRowArray();
            if (! $record) return false;
            if ($ingredient['opening_stock'] > 0 && ! $db->table('ingredient_movements')
                    ->where(['ingredient_id' => $record['id'], 'movement_type' => 'opening'])->countAllResults()) return false;
        }
        return true;
    }

    private static function recipeSeedIsSatisfied(BaseConnection $db): bool
    {
        if (! NutritionCatalogAudit::assess(IngredientSeeder::rows(), RecipeSeeder::rows())['ready']) return false;
        if (! $db->tableExists('product_recipes')) return false;
        $rows = RecipeSeeder::rows();
        if ($rows === []) return false;
        foreach ($rows as $recipe) {
            $product = $db->table('products')->select('id')->where('slug', $recipe['product_slug'])->get()->getRowArray();
            if (! $product || ! $db->table('product_recipes')->where('product_id', $product['id'])->countAllResults()) return false;
        }
        return true;
    }

    private static function notificationSeedIsSatisfied(BaseConnection $db): bool
    {
        if (! $db->tableExists('users') || ! $db->tableExists('notifications')) {
            return false;
        }

        $userCount = $db->table('users')->countAllResults();
        if ($userCount === 0) {
            return false;
        }

        $notifiedUsers = $db->table('notifications')
            ->select('user_id')
            ->where('type', 'demo_ready')
            ->groupBy('user_id')
            ->get()
            ->getNumRows();

        return $notifiedUsers >= $userCount;
    }
}
