<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;

class UserController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/users/index', [
            'users' => (new UserModel())->orderBy('created_at', 'DESC')->findAll(),
        ]);
    }

    public function save()
    {
        if (! $this->validate([
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|min_length[8]',
        ])) {
            return redirect()->to('/admin/users')->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($model->findByEmail($email)) {
            return redirect()->to('/admin/users')->withInput()->with('error', 'That email address is already registered.');
        }

        $created = $model->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
        ]);

        if (! $created) {
            return redirect()->to('/admin/users')->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('/admin/users')->with('success', 'Administrator account created. The administrator can add a profile photo in My settings.');
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/users')->with('error', 'Invalid account status.');
        }

        $currentUserId = (int) (session()->get('user')['id'] ?? 0);
        if ($id === $currentUserId) {
            return redirect()->to('/admin/users')->with('error', 'You cannot change the status of your own account.');
        }

        $model = new UserModel();
        if (! $model->find($id)) {
            return redirect()->to('/admin/users')->with('error', 'User account not found.');
        }
        if (! $model->update($id, ['status' => $status])) {
            return redirect()->to('/admin/users')->with('errors', $model->errors());
        }

        return redirect()->to('/admin/users')->with('success', 'User status updated.');
    }
}
