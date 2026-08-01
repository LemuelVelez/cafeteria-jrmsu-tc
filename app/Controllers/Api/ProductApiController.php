<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Services\MediaStorageService;
use Throwable;

class ProductApiController extends BaseController
{
    public function index()
    {
        return $this->jsonSuccess('Products loaded.', (new ProductModel())->menu());
    }

    public function save(?int $id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
        $model = new ProductModel();

        if ($id && ! $model->find($id)) {
            return $this->jsonError('Product not found.', null, 404);
        }

        $categoryId = (int) ($payload['category_id'] ?? 0);
        if (! (new CategoryModel())->where(['id' => $categoryId, 'is_active' => 1])->first()) {
            return $this->jsonError('Select an active product category.');
        }

        $data = [
            'category_id' => $categoryId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'slug' => url_title((string) ($payload['name'] ?? ''), '-', true),
            'description' => trim((string) ($payload['description'] ?? '')),
            'price' => (float) ($payload['price'] ?? 0),
            'stock' => (int) ($payload['stock'] ?? 0),
            'is_available' => (int) ($payload['is_available'] ?? 1),
            'is_featured' => (int) ($payload['is_featured'] ?? 0),
        ];

        try {
            $ok = $id ? $model->update($id, $data) : $model->insert($data);
        } catch (Throwable $exception) {
            log_message('error', 'API product save failed: {message}', ['message' => $exception->getMessage()]);

            return $this->jsonError('The product could not be saved. Check that its name is unique.');
        }

        return $ok
            ? $this->jsonSuccess(
                'Product saved.',
                $model->find($id ?: $model->getInsertID()),
                $id ? 200 : 201,
            )
            : $this->jsonError('Product validation failed.', $model->errors());
    }

    public function delete(int $id)
    {
        $model = new ProductModel();
        $product = $model->find($id);
        if (! $product) {
            return $this->jsonError('Product not found.', null, 404);
        }

        try {
            $deleted = $model->delete($id);
        } catch (Throwable $exception) {
            log_message('error', 'API product delete failed: {message}', ['message' => $exception->getMessage()]);
            $deleted = false;
        }
        if (! $deleted) {
            return $this->jsonError('The product could not be removed.', $model->errors());
        }

        if (! empty($product['image'])) {
            (new MediaStorageService())->delete($product['image']);
        }

        return $this->jsonSuccess('Product removed.');
    }
}
