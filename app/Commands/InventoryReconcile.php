<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InventoryReconcile extends BaseCommand
{
    protected $group = 'Inventory';
    protected $name = 'inventory:reconcile';
    protected $description = 'Verify product stock against the inventory movement ledger and completed-order sales.';

    public function run(array $params): void
    {
        $db = db_connect();
        $products = $db->table('products')->select('id,name,stock')->where('deleted_at', null)->get()->getResultArray();
        $mismatches = 0;

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $ledgerStock = (int) ($db->query('SELECT COALESCE(SUM(quantity),0) qty FROM inventory_movements WHERE product_id=?', [$productId])->getRow('qty') ?? 0);
            if ($ledgerStock !== (int) $product['stock']) {
                CLI::error(sprintf('%s: stock=%d ledger=%d', $product['name'], $product['stock'], $ledgerStock));
                $mismatches++;
            }

            $completedSold = (int) ($db->query(
                "SELECT COALESCE(SUM(oi.quantity),0) qty FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=? AND o.status='completed'",
                [$productId],
            )->getRow('qty') ?? 0);
            $ledgerNetSold = abs((int) ($db->query(
                "SELECT COALESCE(SUM(CASE WHEN movement_type='sale' THEN quantity WHEN movement_type='cancel_restock' THEN quantity ELSE 0 END),0) qty FROM inventory_movements WHERE product_id=?",
                [$productId],
            )->getRow('qty') ?? 0));
            if ($completedSold !== $ledgerNetSold) {
                CLI::error(sprintf('%s: completed units=%d ledger net sale=%d', $product['name'], $completedSold, $ledgerNetSold));
                $mismatches++;
            }
        }

        if ($mismatches === 0) {
            CLI::write('Inventory reconciliation passed: zero mismatches.', 'green');
            return;
        }
        CLI::error('Inventory reconciliation found ' . $mismatches . ' mismatch(es).');
    }
}
