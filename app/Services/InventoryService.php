<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class InventoryService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    public function deductForOrder(int $productId, int $quantity, int $orderId, ?int $userId = null): array
    {
        return $this->change($productId, -abs($quantity), 'sale', $orderId, $userId, null, 'Order stock deduction');
    }

    public function restockForCancelledOrder(int $productId, int $quantity, int $orderId, ?int $userId = null): array
    {
        $db = $this->db();
        $exists = $db->table('inventory_movements')->where([
            'product_id' => $productId,
            'order_id' => $orderId,
            'movement_type' => 'cancel_restock',
        ])->countAllResults() > 0;
        if ($exists) {
            $product = $db->query('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId])->getRowArray();
            return $product ?: [];
        }
        return $this->change($productId, abs($quantity), 'cancel_restock', $orderId, $userId, null, 'Restocked after cancellation');
    }

    public function stockIn(int $productId, int $quantity, ?int $userId, ?string $reference = null, ?string $note = null): array
    {
        if ($quantity < 1) {
            throw new \DomainException('Stock-in quantity must be at least 1.');
        }
        return $this->change($productId, $quantity, 'stock_in', null, $userId, $reference, $note);
    }

    public function adjust(int $productId, int $quantityDelta, ?int $userId, string $reason): array
    {
        if ($quantityDelta === 0 || trim($reason) === '') {
            throw new \DomainException('Adjustment quantity and reason are required.');
        }
        return $this->change($productId, $quantityDelta, 'adjustment', null, $userId, null, $reason);
    }

    public const WITHDRAWAL_REASONS = ['damaged', 'expired', 'catering', 'transfer', 'return to supplier', 'staff meal', 'spoilage'];

    public static function validateWithdrawal(string $reason, string $remarks, int $quantity): void
    {
        if ($quantity < 1 || !in_array($reason, self::WITHDRAWAL_REASONS, true) || trim($remarks) === '') {
            throw new \DomainException('Withdrawals require a valid reason, positive quantity, and nonempty remarks.');
        }
    }

    public function stockOut(int $productId, int $quantity, ?int $userId, string $reason, string $remarks = ''): array
    {
        self::validateWithdrawal($reason, $remarks, $quantity);
        return $this->change($productId, -$quantity, 'stock_out', null, $userId, null, $remarks, $reason);
    }

    public function count(int $productId, int $physicalQuantity, ?int $userId, string $reason): array
    {
        if ($physicalQuantity < 0 || trim($reason) === '') {
            throw new \DomainException('A nonnegative physical count and reason are required.');
        }
        $db = $this->db();
        $current = $db->query('SELECT stock FROM products WHERE id=? FOR UPDATE', [$productId])->getRowArray();
        if (! $current) throw new \DomainException('Product not found.');
        $difference = $physicalQuantity - (int) $current['stock'];
        // Record zero-difference counts too; the daily sheet must know the stock was physically counted.
        return $this->change($productId, $difference, 'adjustment', null, $userId, 'PHYSICAL-COUNT', 'Count reconciliation: ' . $reason);
    }

    public function recordWaste(int $productId, int $quantity, ?int $userId, string $reason, string $remarks = ''): array
    {
        self::validateWithdrawal($reason, $remarks, $quantity);
        return $this->change($productId, -$quantity, 'waste', null, $userId, null, $remarks, $reason);
    }

    public function opening(int $productId, int $quantity, ?int $userId = null): array
    {
        $db = $this->db();
        $product = $db->query('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$productId])->getRowArray();
        if (! $product) {
            throw new \DomainException('Product not found.');
        }
        if ((int) $product['stock'] !== 0 || $quantity < 0 ||
            $db->table('inventory_movements')->where('product_id', $productId)->countAllResults() !== 0) {
            throw new \DomainException('Opening stock is only allowed once for a new product with zero stock.');
        }
        if (! $db->table('products')->where('id', $productId)->update(['stock' => $quantity])) {
            throw new \RuntimeException('Unable to set initial product stock.');
        }
        if (! $db->table('inventory_movements')->insert([
            'product_id' => $productId,
            'movement_type' => 'opening',
            'quantity' => $quantity,
            'stock_before' => 0,
            'stock_after' => $quantity,
            'order_id' => null,
            'user_id' => $userId,
            'reference' => null,
            'note' => 'Initial product stock',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('Unable to record opening stock.');
        }
        (new AuditLogService($db))->record('inventory_movement', 'product', $productId,
            ['movement_type' => 'opening', 'quantity' => $quantity, 'stock_before' => 0, 'stock_after' => $quantity], $userId);
        $product['stock'] = $quantity;
        return $product;
    }

    private function change(int $productId, int $delta, string $type, ?int $orderId, ?int $userId, ?string $reference, ?string $note, ?string $reasonCode = null): array
    {
        $db = $this->db();
        $product = $db->query('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$productId])->getRowArray();
        if (! $product) {
            throw new \DomainException('Product not found.');
        }
        $before = (int) $product['stock'];
        $after = $type === 'opening' ? $before + $delta : $before + $delta;
        if ($after < 0) {
            throw new \DomainException('Insufficient stock for this inventory change.');
        }
        if (! $db->table('products')->where('id', $productId)->update(['stock' => $after, 'updated_at' => date('Y-m-d H:i:s')])) {
            throw new \RuntimeException('Unable to update product stock.');
        }
        if (! $db->table('inventory_movements')->insert([
            'product_id' => $productId,
            'movement_type' => $type,
            'quantity' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'order_id' => $orderId,
            'user_id' => $userId,
            'reference' => $reference ? mb_substr(trim($reference), 0, 120) : null,
            'note' => $note ? mb_substr(trim($note), 0, 1000) : null,
            'reason_code' => $reasonCode,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('Unable to record inventory movement.');
        }
        (new AuditLogService($db))->record('inventory_movement', 'product', $productId, ['movement_type' => $type, 'quantity' => $delta, 'stock_before' => $before, 'stock_after' => $after, 'reason_code' => $reasonCode, 'note' => $note], $userId);
        $product['previous_stock'] = $before;
        $product['stock'] = $after;
        return $product;
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }
}
