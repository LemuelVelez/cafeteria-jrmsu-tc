<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogService;
use Throwable;

class UserController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/users/index', ['users' => (new UserModel())->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function save()
    {
        if (! $this->validate([
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|strong_password',
        ])) {
            return redirect()->to('/admin/users')->withInput()->with('errors', $this->validator->getErrors());
        }
        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($model->findByEmail($email)) {
            return redirect()->to('/admin/users')->withInput()->with('error', 'That email address is already registered.');
        }
        try {
            $created = $model->insert([
                'name' => trim((string) $this->request->getPost('name')),
                'email' => $email,
                'phone' => trim((string) $this->request->getPost('phone')),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => 'admin', 'status' => 'active', 'session_version' => 1,
            ], true);
            if ($created) {
                (new AuditLogService())->record('user_created', 'user', (int) $created, ['role' => 'admin']);
            }
        } catch (Throwable $exception) {
            log_message('error', 'Administrator creation failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/users')->withInput()->with('error', 'The administrator account could not be created.');
        }
        if (! $created) {
            return redirect()->to('/admin/users')->withInput()->with('errors', $model->errors());
        }
        return redirect()->to('/admin/users')->with('success', 'Administrator account created.');
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/users')->with('error', 'Invalid account status.');
        }
        if ($id === (int) (session()->get('user')['id'] ?? 0)) {
            return redirect()->to('/admin/users')->with('error', 'You cannot change the status of your own account.');
        }
        return $this->updateStatus($id, $status, '/admin/users', null);
    }

    private function updateStatus(int $id, string $status, string $redirect, ?string $requiredRole)
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $user = $db->query('SELECT * FROM users WHERE id=? FOR UPDATE', [$id])->getRowArray();
            if (! $user || ($requiredRole && $user['role'] !== $requiredRole)) {
                throw new \DomainException('User account not found.');
            }
            $newVersion = (int) ($user['session_version'] ?? 1) + 1;
            if (! $db->table('users')->where('id', $id)->update(['status' => $status, 'session_version' => $newVersion, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Unable to update account status.');
            }
            (new AuditLogService($db))->record('user_status_change', 'user', $id, ['old_status' => $user['status'], 'new_status' => $status]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to update account status.');
            }
            $db->transCommit();
            return redirect()->to($redirect)->with('success', 'User status updated.');
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->to($redirect)->with('error', $exception->getMessage());
        }
    }
}
