<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Services\AuditLogService;
use App\Services\InventoryService;
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

        $categoryId = filter_var($this->request->getPost('category_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || ! (new CategoryModel())->where(['id' => (int) $categoryId, 'is_active' => 1])->first()) {
            return redirect()->to('/admin/products')->withInput()->with('error', 'Select an active product category.');
        }

        $sku = trim((string) $this->request->getPost('sku')) ?: null;
        $barcode = trim((string) $this->request->getPost('barcode')) ?: null;
        foreach (['sku' => $sku, 'barcode' => $barcode] as $field => $value) {
            if ($value !== null) {
                $owner = (new ProductModel())->withDeleted()->where($field, $value)->first();
                if ($owner && (int) $owner['id'] !== (int) ($id ?? 0)) {
                    return redirect()->to('/admin/products')->withInput()->with('error', ucfirst($field) . ' is already assigned to another product.');
                }
            }
        }

        $initialStock = $id === null ? filter_var($this->request->getPost('stock'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null;
        if ($id === null && $initialStock === false) {
            return redirect()->to('/admin/products')->withInput()->with('error', 'Initial stock must be zero or greater.');
        }

        $data = [
            'category_id' => (int) $categoryId,
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => url_title((string) $this->request->getPost('name'), '-', true),
            'sku' => $sku,
            'barcode' => $barcode,
            'description' => trim((string) $this->request->getPost('description')),
            'price' => $this->request->getPost('price'),
            'reorder_level' => $this->request->getPost('reorder_level') === '' ? 5 : $this->request->getPost('reorder_level'),
            'serving_size' => $this->nullablePost('serving_size'),
            'calories' => $this->nullablePost('calories'),
            'protein_g' => $this->nullablePost('protein_g'),
            'carbohydrates_g' => $this->nullablePost('carbohydrates_g'),
            'fat_g' => $this->nullablePost('fat_g'),
            'sugar_g' => $this->nullablePost('sugar_g'),
            'fiber_g' => $this->nullablePost('fiber_g'),
            'sodium_mg' => $this->nullablePost('sodium_mg'),
            'allergens' => $this->nullablePost('allergens'),
            'is_healthy_choice' => $this->request->getPost('is_healthy_choice') ? 1 : 0,
            'is_available' => $this->request->getPost('is_available') ? 1 : 0,
            'is_featured' => $this->request->getPost('is_featured') ? 1 : 0,
            'image' => $existing['image'] ?? null,
        ];
        if ($id === null) {
            $data['stock'] = (int) $initialStock;
        }

        $image = $this->request->getFile('image');
        $newImagePath = null;
        $media = new MediaStorageService();
        if ($image && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            $imageRule = "is_image[image]|mime_in[image,image/png,image/jpeg,image/webp]|max_size[image,{$maxKb}]|max_dims[image,2400,2400]";
            if (! $image->isValid() || $image->hasMoved() || ! $this->validate(['image' => $imageRule])) {
                return redirect()->to('/admin/products')->withInput()->with('errors', $this->validator?->getErrors() ?: ['image' => 'The product image is invalid.']);
            }
            try {
                $newImagePath = $media->store($image, 'products');
                $data['image'] = $newImagePath;
            } catch (Throwable $exception) {
                log_message('error', 'Product media upload failed: {message}', ['message' => $exception->getMessage()]);
                return redirect()->to('/admin/products')->withInput()->with('error', 'The product image could not be uploaded.');
            }
        }

        $db = db_connect();
        $db->transBegin();
        try {
            if ($id !== null) {
                $locked = $db->query('SELECT * FROM products WHERE id=? FOR UPDATE', [$id])->getRowArray();
                if (! $locked) {
                    throw new \DomainException('Product not found.');
                }
                $ok = $model->update($id, $data);
                $productId = $id;
            } else {
                $ok = $model->insert($data, true);
                $productId = (int) $ok;
            }
            if (! $ok) {
                throw new \DomainException(implode(' ', $model->errors()) ?: 'Product validation failed.');
            }
            if ($id === null) {
                (new InventoryService($db))->opening($productId, (int) $initialStock, (int) (session()->get('user')['id'] ?? 0));
            }
            $newProduct = $model->find($productId);
            (new AuditLogService($db))->record(
                $id === null ? 'product_created' : 'product_updated',
                'product',
                $productId,
                [
                    'old_price' => $existing['price'] ?? null,
                    'new_price' => $newProduct['price'] ?? null,
                    'old_reorder_level' => $existing['reorder_level'] ?? null,
                    'new_reorder_level' => $newProduct['reorder_level'] ?? null,
                ],
            );
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to save the product.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            $media->delete($newImagePath);
            return redirect()->to('/admin/products')->withInput()->with('error', $exception->getMessage());
        }

        if ($newImagePath !== null && ! empty($existing['image'])) {
            $media->delete((string) $existing['image']);
        }
        return redirect()->to('/admin/products')->with('success', 'Product saved.');
    }

    public function labels(): string
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getGet('ids'))));
        $model = new ProductModel();
        $products = $ids ? $model->whereIn('id', $ids)->findAll() : $model->where('barcode IS NOT NULL', null, false)->findAll();
        return $this->render('admin/products/labels', ['title' => 'Print product labels', 'products' => $products]);
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

    private function nullablePost(string $key): mixed
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }
}
