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

    /** Low-risk read acknowledgment (documented audit exemption), still atomic. */
    public function read(int $id)
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $db = db_connect();
        $db->transBegin();
        try {
            $row = $db->query('SELECT id, read_at FROM notifications WHERE id=? AND user_id=? FOR UPDATE',
                [$id, $userId])->getRowArray();
            if (! $row) {
                $db->transRollback();
                return $this->jsonError('Notification not found.', null, 404);
            }
            if ($row['read_at'] === null) {
                $db->table('notifications')->where('id', $id)->where('user_id', $userId)
                    ->update(['read_at' => date('Y-m-d H:i:s')]);
            }
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to acknowledge the notification.');
            }
            $db->transCommit();
            return $this->jsonSuccess('Notification marked as read.');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Notification acknowledgment failed: {message}', ['message' => $error->getMessage()]);
            return $this->jsonError('Unable to update notification right now.', null, 500);
        }
    }

    /** A bulk acknowledgment must not partially apply on database failure. */
    public function readAll()
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $db = db_connect();
        $db->transBegin();
        try {
            $db->table('notifications')->where('user_id', $userId)->where('read_at', null)
                ->update(['read_at' => date('Y-m-d H:i:s')]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to acknowledge notifications.');
            }
            $db->transCommit();
            return $this->jsonSuccess('All notifications marked as read.');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Bulk notification acknowledgment failed: {message}', ['message' => $error->getMessage()]);
            return $this->jsonError('Unable to update notifications right now.', null, 500);
        }
    }
}
