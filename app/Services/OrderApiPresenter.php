<?php

namespace App\Services;

/** Stable public API shape: never serialize entire model/database rows. */
final class OrderApiPresenter
{
    private const PUBLIC_FIELDS = [
        'id','order_number','order_type','status','subtotal','discount','delivery_fee','total',
        'payment_method','payment_status','created_at','updated_at','rider_id',
        'queue_number','queue_date',
    ];

    public static function present(array $order, array $actor): array
    {
        $result = array_intersect_key($order, array_flip(self::PUBLIC_FIELDS));
        $result['queue_label'] = format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, (string) ($order['order_number'] ?? ''));
        $role = (string) ($actor['role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        $owner = $role === 'customer' && $actorId > 0 && $actorId === (int) ($order['customer_id'] ?? 0);
        $staff = in_array($role, ['admin','cashier'], true);
        $assignedRider = $role === 'rider' && $actorId > 0 && $actorId === (int) ($order['rider_id'] ?? 0);
        $riderActive = $assignedRider && in_array($order['status'] ?? '', ['ready_for_pickup','out_for_delivery'], true);
        if ($owner || $staff || $riderActive) {
            $result['delivery_address'] = $order['delivery_address'] ?? null;
        }
        // Notes may contain private delivery instructions; do not persist them in rider
        // API responses once the delivery is finished or cancelled.
        if ($owner || $staff || $riderActive) {
            $result['notes'] = $order['notes'] ?? null;
        }
        return $result;
    }

    /** Keep customer-entered item notes away from completed rider deliveries. */
    public static function items(array $items, array $order, array $actor): array
    {
        $fields = ['id','product_id','product_name','quantity','unit_price','addon_total','line_total','addons_json'];
        $role = (string) ($actor['role'] ?? '');
        $canSeeNotes = $role !== 'rider' || in_array($order['status'] ?? '', ['ready_for_pickup','out_for_delivery'], true);
        if ($canSeeNotes) $fields[] = 'notes';
        return array_map(static fn (array $line): array => array_intersect_key($line, array_flip($fields)), $items);
    }
}
