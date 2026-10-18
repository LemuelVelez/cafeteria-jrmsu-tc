<?php
namespace App\Controllers;
use App\Models\NotificationModel;

class NotificationController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $filter = (string) $this->request->getGet('filter');
        if (!in_array($filter, ['', 'unread', 'order', 'inventory', 'system'], true)) $filter = '';
        $model = new NotificationModel();
        $builder = $model->where('user_id', $userId);
        if ($filter === 'unread') $builder->where('read_at', null);
        elseif ($filter === 'order') $builder->whereIn('type', ['order_placed','new_order','order_status','order_cancelled','rider_assigned','rider_unassigned']);
        elseif ($filter === 'inventory') $builder->whereIn('type', ['low_stock','out_of_stock','inventory_discrepancy']);
        elseif ($filter === 'system') $builder->whereNotIn('type', ['order_placed','new_order','order_status','order_cancelled','rider_assigned','rider_unassigned','low_stock','out_of_stock','inventory_discrepancy']);
        return $this->render('notifications/index', [
            'title'=>'Notifications', 'filter'=>$filter,
            'notifications'=>$builder->orderBy('created_at', 'DESC')->paginate(20),
            'pager'=>$model->pager,
        ]);
    }
}
