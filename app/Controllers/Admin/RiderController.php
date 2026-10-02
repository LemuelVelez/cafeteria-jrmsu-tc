<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogService;
use Throwable;

class RiderController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/riders/index', ['users' => (new UserModel())->where('role', 'rider')->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function save()
    {
        if (! $this->validate([
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|strong_password',
        ])) {
            return redirect()->to('/admin/riders')->withInput()->with('errors', $this->validator->getErrors());
        }
        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($model->findByEmail($email)) {
            return redirect()->to('/admin/riders')->withInput()->with('error', 'That email address is already registered.');
        }
        try {
            $created = $model->insert([
                'name' => trim((string) $this->request->getPost('name')),
                'email' => $email,
                'phone' => trim((string) $this->request->getPost('phone')),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => 'rider', 'status' => 'active', 'session_version' => 1,
            ], true);
            if ($created) {
                (new AuditLogService())->record('rider_created', 'user', (int) $created, ['role' => 'rider']);
            }
        } catch (Throwable $exception) {
            log_message('error', 'Rider creation failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/riders')->withInput()->with('error', 'The rider account could not be created.');
        }
        if (! $created) {
            return redirect()->to('/admin/riders')->withInput()->with('errors', $model->errors());
        }
        return redirect()->to('/admin/riders')->with('success', 'Rider account created.');
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/riders')->with('error', 'Invalid account status.');
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $rider = $db->query("SELECT * FROM users WHERE id=? AND role='rider' FOR UPDATE", [$id])->getRowArray();
            if (! $rider) {
                throw new \DomainException('Rider account not found.');
            }
            $db->table('users')->where('id', $id)->update(['status' => $status, 'session_version' => (int) ($rider['session_version'] ?? 1) + 1, 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('rider_status_change', 'user', $id, ['old_status' => $rider['status'], 'new_status' => $status]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to update rider status.');
            }
            $db->transCommit();
            return redirect()->to('/admin/riders')->with('success', 'Rider status updated.');
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->to('/admin/riders')->with('error', $exception->getMessage());
        }
    }
}
