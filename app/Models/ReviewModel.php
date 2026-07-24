<?php

namespace App\Models;

class ReviewModel extends BaseModel
{
    protected $table = 'reviews';
    protected $primaryKey = 'id';
    protected $allowedFields = ['order_id', 'product_id', 'customer_id', 'rating', 'comment', 'is_visible'];
    protected $validationRules = [
        'order_id' => 'required|is_natural_no_zero',
        'customer_id' => 'required|is_natural_no_zero',
        'rating' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
        'comment' => 'permit_empty|max_length[1000]',
    ];
}
