<?php

namespace App\Controllers;

use App\Models\NotificationModel;

class NotificationController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $model = new NotificationModel();
        return $this->render('notifications/index', [
            'title' => 'Notifications',
            'notifications' => $model->where('user_id', $userId)->orderBy('created_at', 'DESC')->paginate(20),
            'pager' => $model->pager,
        ]);
    }
}
