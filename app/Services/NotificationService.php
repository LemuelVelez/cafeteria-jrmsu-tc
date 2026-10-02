<?php

namespace App\Services;

use App\Enums\OrderStatus;
use CodeIgniter\Database\BaseConnection;

class NotificationService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    public function orderPlaced(array $order): void
    {
        $db = $this->db();
        if (! empty($order['customer_id'])) {
            $this->insert((int) $order['customer_id'], (int) $order['id'], 'order_placed', 'Order placed', 'Your order ' . $order['order_number'] . ' was received.', '/customer/orders/' . $order['id']);
        }
        // Staff alerts are for online pending orders only; POS orders are already being handled by the cashier.
        if (empty($order['cashier_id']) && ($order['status'] ?? '') === OrderStatus::Pending->value) {
            foreach ($this->staffUsers(['admin', 'cashier']) as $staff) {
                $link = $staff['role'] === 'admin' ? '/admin/orders/' . $order['id'] : '/cashier/orders';
                $this->insert((int) $staff['id'], (int) $order['id'], 'new_order', 'New order', 'New order ' . $order['order_number'] . ' is waiting for staff.', $link);
            }
        }
    }

    public function statusChanged(array $order, string $status, ?string $note = null): void
    {
        $enum = OrderStatus::tryFrom($status);
        if (! $enum) {
            return;
        }
        if (! empty($order['customer_id'])) {
            $message = $enum->customerDescription();
            if ($status === OrderStatus::Cancelled->value && trim((string) $note) !== '') {
                $message .= ' Reason: ' . mb_substr(trim((string) $note), 0, 180);
            }
            $this->insert((int) $order['customer_id'], (int) $order['id'], 'order_status', $enum->label(), $message, '/customer/orders/' . $order['id']);
            $this->emailOrderStatusIfEnabled($order, $enum, $message);
        }
        if ($status === OrderStatus::Cancelled->value) {
            foreach ($this->staffUsers(['admin', 'cashier']) as $staff) {
                $link = $staff['role'] === 'admin' ? '/admin/orders/' . $order['id'] : '/cashier/orders';
                $this->insert((int) $staff['id'], (int) $order['id'], 'order_cancelled', 'Order cancelled', 'Order ' . $order['order_number'] . ' was cancelled.', $link);
            }
        }
    }

    public function riderAssigned(array $order, ?int $oldRiderId, ?int $newRiderId): void
    {
        if ($oldRiderId && $oldRiderId !== $newRiderId) {
            $this->insert($oldRiderId, (int) $order['id'], 'rider_unassigned', 'Delivery assignment removed', 'Order ' . $order['order_number'] . ' is no longer assigned to you.', '/rider/deliveries');
        }
        if ($newRiderId) {
            $this->insert($newRiderId, (int) $order['id'], 'rider_assigned', 'New delivery assignment', 'Order ' . $order['order_number'] . ' has been assigned to you.', '/rider/deliveries/' . $order['id']);
        }
    }

    public function lowStock(array $product): void
    {
        $stock = (int) ($product['stock'] ?? 0);
        $level = (int) ($product['reorder_level'] ?? 5);
        if ($stock > $level) {
            return;
        }
        foreach ($this->staffIds(['admin']) as $userId) {
            $type = $stock === 0 ? 'out_of_stock' : 'low_stock';
            $this->insert($userId, null, $type, $stock === 0 ? 'Out of stock' : 'Low stock', ($product['name'] ?? 'Product') . ' has ' . $stock . ' unit(s) remaining.', '/admin/inventory');
        }
    }

    private function emailOrderStatusIfEnabled(array $order, OrderStatus $status, string $message): void
    {
        if (! in_array($status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery, OrderStatus::Completed, OrderStatus::Cancelled], true)) return;
        try {
            $enabled = $this->db()->table('settings')->where('setting_key', 'email_order_notifications')->get()->getRow('setting_value');
            if ((string) ($enabled ?? '1') !== '1') return;
            $user = $this->db()->table('users')->select('id,name,email')->where('id', (int) ($order['customer_id'] ?? 0))->get()->getRowArray();
            if (! $user || empty($user['email'])) return;
            (new AccountEmailService())->sendOrderStatus($user, $order, $status->label(), $message);
        } catch (\Throwable $exception) {
            log_message('error', 'Order status email failed for order {orderId}: {message}', ['orderId' => (int) ($order['id'] ?? 0), 'message' => $exception->getMessage()]);
        }
    }

    private function insert(int $userId, ?int $orderId, string $type, string $title, string $message, ?string $link): void
    {
        $this->db()->table('notifications')->insert([
            'user_id' => $userId,
            'order_id' => $orderId,
            'type' => $type,
            'title' => mb_substr($title, 0, 160),
            'message' => mb_substr($message, 0, 500),
            'link' => $link,
            'read_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<int> */
    private function staffIds(array $roles): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $this->staffUsers($roles));
    }

    /** @return list<array{id:int|string,role:string}> */
    private function staffUsers(array $roles): array
    {
        return $this->db()->table('users')->select('id,role')->whereIn('role', $roles)->where('status', 'active')->get()->getResultArray();
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }
}
