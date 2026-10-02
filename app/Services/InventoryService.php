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

    public function recordWaste(int $productId, int $quantity, ?int $userId, string $reason): array
    {
        if ($quantity < 1 || trim($reason) === '') {
            throw new \DomainException('Waste quantity and reason are required.');
        }
        return $this->change($productId, -$quantity, 'waste', null, $userId, null, $reason);
    }

    public function opening(int $productId, int $quantity, ?int $userId = null): array
    {
        $db = $this->db();
        $product = $db->query('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$productId])->getRowArray();
        if (! $product) {
            throw new \DomainException('Product not found.');
        }
        if ((int) $product['stock'] !== $quantity || $quantity < 0) {
            throw new \DomainException('Opening balance must match the product initial stock.');
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
        return $product;
    }

    private function change(int $productId, int $delta, string $type, ?int $orderId, ?int $userId, ?string $reference, ?string $note): array
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
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('Unable to record inventory movement.');
        }
        $product['stock'] = $after;
        return $product;
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }
}
