<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Services\MediaStorageService;
use Throwable;

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
        $newImagePath = null;
        $media = new MediaStorageService();

        if ($image && $image->isValid() && ! $image->hasMoved()) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            if (! $this->validate(['image' => "is_image[image]|mime_in[image,image/png,image/jpeg,image/webp]|max_size[image,{$maxKb}]|max_dims[image,2400,2400]"])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            try {
                $newImagePath = $media->store($image, 'products');
                $data['image'] = $newImagePath;
            } catch (Throwable $exception) {
                log_message('error', 'Product media upload failed: {message}', ['message' => $exception->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'The product image could not be uploaded. Check the media storage configuration.');
            }
        }

        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        if (! $ok) {
            if ($newImagePath) {
                $media->delete($newImagePath);
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        if ($newImagePath && ! empty($existing['image'])) {
            $media->delete($existing['image']);
        }

        return redirect()->to('/admin/products')->with('success', 'Product saved.');
    }

    public function delete(int $id)
    {
        $model = new ProductModel();
        $product = $model->find($id);
        $deleted = $model->delete($id);

        if ($deleted && ! empty($product['image'])) {
            (new MediaStorageService())->delete($product['image']);
        }

        return $deleted
            ? redirect()->to('/admin/products')->with('success', 'Product removed.')
            : redirect()->back()->with('error', 'The product could not be removed.');
    }
}
