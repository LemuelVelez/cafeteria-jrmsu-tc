<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** The canonical product-stock thresholds and display contract (not ingredient stock). */
final class StockLevelPolicy
{
    public const DEFAULTS = [
        'inventory_level_yellow_percent' => 50,
        'inventory_level_red_percent' => 25,
        'inventory_alert_half_enabled' => 1,
    ];

    /** @return array{level:string,percent:int,label:string,cssClass:string} */
    public static function evaluate(int $stock, int $reorderLevel, int $maxStock, int $yellowPct = 50, int $redPct = 25): array
    {
        $stock = max(0, $stock);
        $reorderLevel = max(0, $reorderLevel);
        // A missing/zero par level has no meaningful percentage: use the reorder threshold only.
        $percent = $maxStock > 0 ? (int) min(100, max(0, round($stock / $maxStock * 100))) : 0;
        $level = $stock === 0 ? 'empty'
            : ($stock <= $reorderLevel || ($maxStock > 0 && $percent <= $redPct) ? 'red'
            : ($maxStock > 0 && $percent <= $yellowPct ? 'yellow' : 'green'));
        return ['level' => $level, 'percent' => $percent,
            'label' => ['empty' => 'Out of stock', 'red' => 'Low', 'yellow' => 'Half stock', 'green' => 'Well stocked'][$level],
            'cssClass' => 'stock-level--' . $level];
    }

    /** @param array<string,mixed> $settings */
    public static function forProduct(array $product, array $settings = []): array
    {
        return self::evaluate((int) ($product['stock'] ?? 0), (int) ($product['reorder_level'] ?? 0),
            (int) ($product['max_stock'] ?? 0),
            (int) ($settings['inventory_level_yellow_percent'] ?? 50),
            (int) ($settings['inventory_level_red_percent'] ?? 25));
    }

    /** @return array<string,int> */
    public static function settings(?BaseConnection $db = null): array
    {
        $values = self::DEFAULTS;
        $rows = ($db ?? db_connect())->table('settings')->select('setting_key,setting_value')
            ->whereIn('setting_key', array_keys($values))->get()->getResultArray();
        foreach ($rows as $row) {
            $key = (string) $row['setting_key'];
            if (preg_match('/^\d+$/', (string) $row['setting_value'])) {
                $values[$key] = (int) $row['setting_value'];
            }
        }
        if ($values['inventory_level_red_percent'] < 1 || $values['inventory_level_yellow_percent'] > 99
            || $values['inventory_level_yellow_percent'] < 1 || $values['inventory_level_red_percent'] >= $values['inventory_level_yellow_percent']) {
            $values['inventory_level_red_percent'] = 25;
            $values['inventory_level_yellow_percent'] = 50;
        }
        $values['inventory_alert_half_enabled'] = $values['inventory_alert_half_enabled'] === 0 ? 0 : 1;
        return $values;
    }

    /** @return array{red:int,yellow:int,empty:int,green:int,warnings:list<array<string,mixed>>} */
    public static function summary(array $products, array $settings): array
    {
        $result = ['red' => 0, 'yellow' => 0, 'empty' => 0, 'green' => 0, 'warnings' => []];
        foreach ($products as $product) {
            $status = self::forProduct($product, $settings);
            $result[$status['level']]++;
            if (in_array($status['level'], ['red','empty'], true)) $result['warnings'][] = $product;
        }
        return $result;
    }
}
