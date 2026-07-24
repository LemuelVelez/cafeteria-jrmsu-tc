<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;

class CustomerController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/customers/index', [
            'users' => (new UserModel())->where('role', 'customer')->orderBy('created_at', 'DESC')->findAll(),
            'roleLabel' => 'Customers',
        ]);
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/customers')->with('error', 'Invalid account status.');
        }

        $model = new UserModel();
        if (! $model->where('role', 'customer')->find($id)) {
            return redirect()->to('/admin/customers')->with('error', 'Customer account not found.');
        }
        if (! $model->update($id, ['status' => $status])) {
            return redirect()->to('/admin/customers')->with('errors', $model->errors());
        }

        return redirect()->to('/admin/customers')->with('success', 'Account status updated.');
    }
}
