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
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $model = new UserModel();
        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))),
            'phone' => trim((string) $this->request->getPost('phone')),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'rider',
            'status' => 'active',
        ];
        return $model->insert($data) ? redirect()->back()->with('success', 'Rider account created.') : redirect()->back()->withInput()->with('errors', $model->errors());
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->back()->with('error', 'Invalid account status.');
        }
        $model = new UserModel();
        if (! $model->update($id, ['status' => $status])) {
            return redirect()->back()->with('errors', $model->errors());
        }
        return redirect()->back()->with('success', 'Rider status updated.');
    }
}
