<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductModel;

class ProductController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/products/index', [
            'products' => (new ProductModel())->withCategory(),
            'categories' => (new CategoryModel())->where('is_active', 1)->orderBy('name')->findAll(),
        ]);
    }

    public function save(?int $id = null)
    {
        $model = new ProductModel();
        $existing = $id ? $model->find($id) : null;
        $data = [
            'category_id' => (int) $this->request->getPost('category_id'),
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => url_title((string) $this->request->getPost('name'), '-', true),
            'description' => trim((string) $this->request->getPost('description')),
            'price' => (float) $this->request->getPost('price'),
            'stock' => (int) $this->request->getPost('stock'),
            'is_available' => $this->request->getPost('is_available') ? 1 : 0,
            'is_featured' => $this->request->getPost('is_featured') ? 1 : 0,
            'image' => $existing['image'] ?? null,
        ];
        $image = $this->request->getFile('image');
        if ($image && $image->isValid() && ! $image->hasMoved()) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            if (! $this->validate(['image' => "is_image[image]|mime_in[image,image/png,image/jpeg,image/webp]|max_size[image,{$maxKb}]|max_dims[image,2400,2400]"])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
            $name = $image->getRandomName();
            $image->move(FCPATH . 'uploads/products', $name);
            $data['image'] = 'uploads/products/' . $name;
        }
        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        return $ok ? redirect()->to('/admin/products')->with('success', 'Product saved.') : redirect()->back()->withInput()->with('errors', $model->errors());
    }

    public function delete(int $id)
    {
        (new ProductModel())->delete($id);
        return redirect()->to('/admin/products')->with('success', 'Product removed.');
    }
}
