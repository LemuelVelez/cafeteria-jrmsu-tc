<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;

class OrderModel extends BaseModel
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'order_number', 'customer_id', 'cashier_id', 'rider_id', 'order_type', 'status',
        'subtotal', 'discount', 'delivery_fee', 'total', 'payment_method', 'payment_status',
        'delivery_address', 'notes', 'promo_id',
    ];
    protected $validationRules = [
        'order_number' => 'required|max_length[40]',
        'status' => 'required|in_list[pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled]',
    ];

    protected function initialize(): void
    {
        $this->validationRules['order_type'] = 'required|in_list[' . implode(',', OrderType::values()) . ']';
        $this->validationRules['payment_method'] = 'required|in_list[' . implode(',', PaymentMethod::values()) . ']';
    }

    public function detailed(?array $where = null): array
    {
        $builder = $this->select('orders.*, customer.name AS customer_name, customer.email AS customer_email, customer.phone AS customer_phone, cashier.name AS cashier_name, rider.name AS rider_name')
            ->join('users customer', 'customer.id = orders.customer_id', 'left')
            ->join('users cashier', 'cashier.id = orders.cashier_id', 'left')
            ->join('users rider', 'rider.id = orders.rider_id', 'left');
        if ($where) {
            $builder->where($where);
        }
        return $builder->orderBy('orders.created_at', 'DESC')->findAll();
    }

    public function todaySummary(?int $cashierId = null): array
    {
        $builder = $this->select('COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS revenue')
            ->where('DATE(created_at)', date('Y-m-d'))
            ->where('status !=', 'cancelled');
        if ($cashierId) {
            $builder->where('cashier_id', $cashierId);
        }
        return $builder->first() ?? ['order_count' => 0, 'revenue' => 0];
    }
}
