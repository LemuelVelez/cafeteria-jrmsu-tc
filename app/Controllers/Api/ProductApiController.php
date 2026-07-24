<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ProductModel;

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
        $data = [
            'category_id' => (int) ($payload['category_id'] ?? 0),
            'name' => trim((string) ($payload['name'] ?? '')),
            'slug' => url_title((string) ($payload['name'] ?? ''), '-', true),
            'description' => trim((string) ($payload['description'] ?? '')),
            'price' => (float) ($payload['price'] ?? 0),
            'stock' => (int) ($payload['stock'] ?? 0),
            'is_available' => (int) ($payload['is_available'] ?? 1),
            'is_featured' => (int) ($payload['is_featured'] ?? 0),
        ];
        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        return $ok ? $this->jsonSuccess('Product saved.', $id ? $model->find($id) : $model->find($model->getInsertID()), $id ? 200 : 201) : $this->jsonError('Product validation failed.', $model->errors());
    }

    public function delete(int $id)
    {
        (new ProductModel())->delete($id);
        return $this->jsonSuccess('Product removed.');
    }
}
