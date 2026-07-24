<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;

class RiderController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/riders/index', ['users' => (new UserModel())->where('role', 'rider')->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function save()
    {
        if (! $this->validate(['name' => 'required|min_length[2]|max_length[100]', 'email' => 'required|valid_email|max_length[160]', 'password' => 'required|min_length[8]'])) {
            return redirect()->to('/admin/riders')->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($model->findByEmail($email)) {
            return redirect()->to('/admin/riders')->withInput()->with('error', 'That email address is already registered.');
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'rider',
            'status' => 'active',
        ];

        if (! $model->insert($data)) {
            return redirect()->to('/admin/riders')->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider account created. The rider can add a profile photo in My settings.');
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/riders')->with('error', 'Invalid account status.');
        }

        $model = new UserModel();
        $rider = $model->where('role', 'rider')->find($id);
        if (! $rider) {
            return redirect()->to('/admin/riders')->with('error', 'Rider account not found.');
        }
        if (! $model->update($id, ['status' => $status])) {
            return redirect()->to('/admin/riders')->with('errors', $model->errors());
        }

        return redirect()->to('/admin/riders')->with('success', 'Rider status updated.');
    }
}
