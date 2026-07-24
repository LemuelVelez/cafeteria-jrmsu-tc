<?php

namespace App\Models;

class PromoUsageModel extends BaseModel
{
    protected $table = 'promo_usages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['promo_id', 'order_id', 'user_id'];
}
