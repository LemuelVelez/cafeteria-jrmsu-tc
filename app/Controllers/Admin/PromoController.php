<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PromoModel;

class PromoController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/promos/index', ['promos' => (new PromoModel())->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function save(?int $id = null)
    {
        $model = new PromoModel();
        $data = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'description' => trim((string) $this->request->getPost('description')),
            'discount_type' => (string) $this->request->getPost('discount_type'),
            'discount_value' => (float) $this->request->getPost('discount_value'),
            'minimum_order' => (float) $this->request->getPost('minimum_order'),
            'starts_at' => $this->request->getPost('starts_at') ?: null,
            'ends_at' => $this->request->getPost('ends_at') ?: null,
            'usage_limit' => (int) $this->request->getPost('usage_limit'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        return $ok ? redirect()->back()->with('success', 'Promotion saved.') : redirect()->back()->withInput()->with('errors', $model->errors());
    }
}
