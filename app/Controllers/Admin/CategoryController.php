<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use Throwable;

class CategoryController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/categories/index', [
            'categories' => (new CategoryModel())->orderBy('sort_order')->findAll(),
        ]);
    }

    public function save(?int $id = null)
    {
        $model = new CategoryModel();
        if ($id !== null && ! $model->find($id)) {
            return redirect()->to('/admin/categories')->with('error', 'Category not found.');
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => url_title((string) $this->request->getPost('name'), '-', true),
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        try {
            $ok = $id !== null ? $model->update($id, $data) : $model->insert($data);
        } catch (Throwable $exception) {
            log_message('error', 'Category save failed: {message}', ['message' => $exception->getMessage()]);

            return redirect()->to('/admin/categories')
                ->withInput()
                ->with('error', 'The category could not be saved.');
        }

        return $ok
            ? redirect()->to('/admin/categories')->with('success', 'Category saved.')
            : redirect()->to('/admin/categories')->withInput()->with('errors', $model->errors());
    }

    public function delete(int $id)
    {
        $model = new CategoryModel();
        if (! $model->find($id)) {
            return redirect()->to('/admin/categories')->with('error', 'Category not found.');
        }

        try {
            $deleted = $model->delete($id);
        } catch (Throwable $exception) {
            log_message('error', 'Category delete failed: {message}', ['message' => $exception->getMessage()]);
            $deleted = false;
        }

        return $deleted
            ? redirect()->to('/admin/categories')->with('success', 'Category removed.')
            : redirect()->to('/admin/categories')->with('error', 'The category could not be removed.');
    }
}
