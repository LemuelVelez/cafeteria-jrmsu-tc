<?php

namespace App\Models;

class OrderItemModel extends BaseModel
{
    protected $table = 'order_items';
    protected $primaryKey = 'id';
    protected $allowedFields = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'addon_total', 'line_total', 'addons_json', 'notes'];
    protected $validationRules = [
        'order_id' => 'required|is_natural_no_zero',
        'product_id' => 'required|is_natural_no_zero',
        'quantity' => 'required|integer|greater_than[0]',
        'unit_price' => 'required|decimal|greater_than_equal_to[0]',
    ];
}
