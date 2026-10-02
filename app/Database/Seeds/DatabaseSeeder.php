<?php

namespace App\Database\Seeds;

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
        'SettingSeeder',
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
            'SettingSeeder' => self::hasAllValues($db, 'settings', 'setting_key', [
                'cafeteria_name',
                'delivery_fee',
                'operating_hours',
                'contact_number',
                'pickup_enabled',
                'delivery_enabled',
                'email_order_notifications',
            ]),
            'PromoSeeder' => self::hasAllValues($db, 'promos', 'code', ['WELCOME10']),
            'NotificationSeeder' => self::notificationSeedIsSatisfied($db),
            default => false,
        };
    }

    public function run(): void
    {
        foreach (self::SEEDERS as $seeder) {
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
