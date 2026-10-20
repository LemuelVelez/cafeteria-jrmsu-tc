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
            if ($status === OrderStatus::OutForDelivery->value) {
                $rider = $this->db()->table('users')->select('name')->where('id',(int)($order['rider_id']??0))->get()->getRowArray();
                if($rider)$message .= ' Your rider is ' . $rider['name'] . '.';
                $message .= ' A four-digit delivery confirmation code is available in your order page while your order is out for delivery. Estimated arrival depends on traffic; please contact the cafeteria for an updated ETA.';
            }
            if ($status === OrderStatus::Cancelled->value && trim((string) $note) !== '') {
                $message .= ' Reason: ' . mb_substr(trim((string) $note), 0, 180);
            }
            $title = $enum->label();
            if ($status === OrderStatus::ReadyForPickup->value && ($order['order_type'] ?? '') === 'pickup') {
                $queue = format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, $order['order_number'] ?? '');
                $title = 'Order ' . $queue . ' is ready';
                $message = 'Order ' . $queue . ' is ready for pickup. Please proceed to the counter.';
            }
            $this->insert((int) $order['customer_id'], (int) $order['id'], 'order_status', $title, $message, '/customer/orders/' . $order['id']);
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
            if (!empty($order['customer_id'])) {
                $rider = $this->db()->table('users')->select('name')->where('id',$newRiderId)->get()->getRowArray();
                $this->insert((int)$order['customer_id'],(int)$order['id'],'rider_assigned','Delivery rider assigned',
                    'Your rider is '.($rider['name']??'assigned').'. You will be notified when they depart.',
                    '/customer/orders/'.$order['id']);
            }
            $this->insert($newRiderId, (int) $order['id'], 'rider_assigned', 'New delivery assignment', 'Order ' . $order['order_number'] . ' has been assigned to you.', '/rider/deliveries/' . $order['id']);
        }
    }

    public function lowStock(array $product): void
    {
        $previous = $product['previous_stock'] ?? null;
        if ($previous === null) return;
        $settings = StockLevelPolicy::settings($this->db());
        $stock = (int) ($product['stock'] ?? 0);
        // Each new worse level is a distinct episode, including red -> empty.
        // Restocking to a better level re-arms subsequent transitions.
        $type = StockAlertPolicy::transition((int) $previous, $stock,
            (int) ($product['reorder_level'] ?? 5), (int) ($product['max_stock'] ?? 0),
            $settings['inventory_level_yellow_percent'], $settings['inventory_level_red_percent'],
            $settings['inventory_alert_half_enabled'] === 1);
        if ($type === null) return;
        $titles = ['stock_half' => 'Stock at half level', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock'];
        foreach ($this->staffUsers(['admin', 'cashier']) as $staff) {
            $link = $staff['role'] === 'cashier' ? '/cashier/inventory' : '/admin/inventory';
            $this->insert((int) $staff['id'], null, $type, $titles[$type],
                ($product['name'] ?? 'Product') . ' has ' . $stock . ' unit(s) remaining.', $link);
        }
    }

    private function emailOrderStatusIfEnabled(array $order, OrderStatus $status, string $message): void
    {
        if (! in_array($status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery, OrderStatus::Completed, OrderStatus::Delivered, OrderStatus::Cancelled], true)) return;
        try {
            $enabled = $this->db()->table('settings')->where('setting_key', 'email_order_notifications')->get()->getRow('setting_value');
            if ((string) ($enabled ?? '1') !== '1') return;
            $user = $this->db()->table('users')->select('id,name,email')->where('id', (int) ($order['customer_id'] ?? 0))->get()->getRowArray();
            if (! $user || empty($user['email'])) return;
            $prefRow=$this->db()->table('users')->select('notification_email_prefs')->where('id',$user['id'])->get()->getRowArray();
            $prefs=json_decode((string)($prefRow['notification_email_prefs']??'null'),true);
            if(is_array($prefs) && empty($prefs[$status->value]))return;
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
        (new WebPushService($this->db()))->enqueue($userId,(int)$this->db()->insertID());
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
