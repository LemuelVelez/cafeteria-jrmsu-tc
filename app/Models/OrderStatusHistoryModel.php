<?php

namespace App\Models;

class OrderStatusHistoryModel extends BaseModel
{
    protected $table = 'order_status_history';
    protected $primaryKey = 'id';
    protected $allowedFields = ['order_id', 'user_id', 'from_status', 'to_status', 'note'];
}
