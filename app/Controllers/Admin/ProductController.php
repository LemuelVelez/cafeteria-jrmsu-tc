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
        $existing = $id !== null ? $model->find($id) : null;
        if ($id !== null && ! $existing) {
            return redirect()->to('/admin/products')->with('error', 'Product not found.');
        }

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

        if ($image && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            $imageRule = "is_image[image]|mime_in[image,image/png,image/jpeg,image/webp]|max_size[image,{$maxKb}]|max_dims[image,2400,2400]";
            if (! $image->isValid() || $image->hasMoved() || ! $this->validate(['image' => $imageRule])) {
                return redirect()->to('/admin/products')
                    ->withInput()
                    ->with('errors', $this->validator?->getErrors() ?: ['image' => 'The product image is invalid.']);
            }

            try {
                $newImagePath = $media->store($image, 'products');
                $data['image'] = $newImagePath;
            } catch (Throwable $exception) {
                log_message('error', 'Product media upload failed: {message}', ['message' => $exception->getMessage()]);

                return redirect()->to('/admin/products')
                    ->withInput()
                    ->with('error', 'The product image could not be uploaded.');
            }
        }

        try {
            $ok = $id !== null ? $model->update($id, $data) : $model->insert($data);
        } catch (Throwable $exception) {
            $media->delete($newImagePath);
            log_message('error', 'Product save failed: {message}', ['message' => $exception->getMessage()]);

            return redirect()->to('/admin/products')
                ->withInput()
                ->with('error', 'The product could not be saved.');
        }

        if (! $ok) {
            $media->delete($newImagePath);

            return redirect()->to('/admin/products')->withInput()->with('errors', $model->errors());
        }

        if ($newImagePath !== null && ! empty($existing['image'])) {
            $media->delete((string) $existing['image']);
        }

        return redirect()->to('/admin/products')->with('success', 'Product saved.');
    }

    public function delete(int $id)
    {
        $model = new ProductModel();
        $product = $model->find($id);
        if (! $product) {
            return redirect()->to('/admin/products')->with('error', 'Product not found.');
        }

        try {
            $deleted = $model->delete($id);
        } catch (Throwable $exception) {
            log_message('error', 'Product delete failed: {message}', ['message' => $exception->getMessage()]);
            $deleted = false;
        }

        if ($deleted && ! empty($product['image'])) {
            (new MediaStorageService())->delete((string) $product['image']);
        }

        return $deleted
            ? redirect()->to('/admin/products')->with('success', 'Product removed.')
            : redirect()->to('/admin/products')->with('error', 'The product could not be removed.');
    }
}
