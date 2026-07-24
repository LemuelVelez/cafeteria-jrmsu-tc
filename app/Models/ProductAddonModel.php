<?php

namespace App\Models;

class ProductAddonModel extends BaseModel
{
    protected $table = 'product_addons';
    protected $primaryKey = 'id';
    protected $allowedFields = ['product_id', 'name', 'price', 'is_active'];
    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'name' => 'required|max_length[100]',
        'price' => 'required|decimal|greater_than_equal_to[0]',
    ];
}
