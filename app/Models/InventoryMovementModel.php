<?php

namespace App\Models;

class InventoryMovementModel extends BaseModel
{
    protected $table = 'inventory_movements';
    protected $primaryKey = 'id';
    protected $allowedFields = ['product_id', 'movement_type', 'quantity', 'stock_before', 'stock_after', 'order_id', 'user_id', 'reference', 'note'];
    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'movement_type' => 'required|in_list[opening,stock_in,sale,cancel_restock,adjustment,waste]',
        'quantity' => 'required|integer',
        'stock_before' => 'required|integer|greater_than_equal_to[0]',
        'stock_after' => 'required|integer|greater_than_equal_to[0]',
    ];
}
