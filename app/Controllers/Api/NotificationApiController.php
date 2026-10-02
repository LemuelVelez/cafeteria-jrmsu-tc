<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\NotificationModel;

class NotificationApiController extends BaseController
{
    public function index()
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $rows = (new NotificationModel())->where('user_id', $userId)->orderBy('created_at', 'DESC')->findAll(20);
        return $this->jsonSuccess('Notifications loaded.', $rows);
    }

    public function unreadCount()
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $count = (new NotificationModel())->where('user_id', $userId)->where('read_at', null)->countAllResults();
        return $this->jsonSuccess('Unread notification count loaded.', ['count' => $count]);
    }

    public function read(int $id)
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $model = new NotificationModel();
        $row = $model->where(['id' => $id, 'user_id' => $userId])->first();
        if (! $row) {
            return $this->jsonError('Notification not found.', null, 404);
        }
        $model->update($id, ['read_at' => $row['read_at'] ?: date('Y-m-d H:i:s')]);
        return $this->jsonSuccess('Notification marked as read.');
    }

    public function readAll()
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        (new NotificationModel())->where('user_id', $userId)->where('read_at', null)->set(['read_at' => date('Y-m-d H:i:s')])->update();
        return $this->jsonSuccess('All notifications marked as read.');
    }
}
