<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;

class CategoryController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/categories/index', ['categories' => (new CategoryModel())->orderBy('sort_order')->findAll()]);
    }

    public function save(?int $id = null)
    {
        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => url_title((string) $this->request->getPost('name'), '-', true),
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
        $model = new CategoryModel();
        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        return $ok ? redirect()->to('/admin/categories')->with('success', 'Category saved.') : redirect()->back()->withInput()->with('errors', $model->errors());
    }

    public function delete(int $id)
    {
        (new CategoryModel())->delete($id);
        return redirect()->to('/admin/categories')->with('success', 'Category removed.');
    }
}
