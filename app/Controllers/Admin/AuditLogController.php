<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;

class AuditLogController extends BaseController
{
    public function index(): string
    {
        $model = new AuditLogModel();
        $action = trim((string) $this->request->getGet('action'));
        $userId = (int) ($this->request->getGet('user_id') ?? 0);
        $builder = $model->select('audit_logs.*, users.name AS user_name')->join('users', 'users.id=audit_logs.user_id', 'left');
        if ($action !== '') {
            $builder->where('audit_logs.action', $action);
        }
        if ($userId > 0) {
            $builder->where('audit_logs.user_id', $userId);
        }
        return $this->render('admin/audit_logs/index', [
            'title' => 'Audit Logs',
            'logs' => $builder->orderBy('audit_logs.created_at', 'DESC')->paginate(30),
            'pager' => $model->pager,
            'action' => $action,
            'userId' => $userId,
        ]);
    }
}
