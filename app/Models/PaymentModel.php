<?php

namespace App\Models;

use App\Enums\PaymentMethod;

class PaymentModel extends BaseModel
{
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $allowedFields = ['order_id', 'method', 'amount', 'status', 'transaction_reference', 'paid_at'];

    protected function initialize(): void
    {
        $this->validationRules = [
            'order_id' => 'required|is_natural_no_zero',
            'method' => 'required|in_list[' . implode(',', PaymentMethod::values()) . ']',
            'amount' => 'required|decimal|greater_than_equal_to[0]',
            'status' => 'permit_empty|in_list[pending,paid,failed,refunded]',
        ];
    }
}
