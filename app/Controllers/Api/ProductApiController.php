<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Services\MediaStorageService;
use Throwable;

class ProductApiController extends BaseController
{
    public function index()
    {
        $products = (new ProductModel())->menu();
        $addonsByProduct = [];
        if ($products !== []) {
            $productIds = array_map('intval', array_column($products, 'id'));
            foreach ((new ProductAddonModel())->whereIn('product_id', $productIds)->where('is_active', 1)->findAll() as $addon) {
                $addonsByProduct[(int) $addon['product_id']][] = $addon;
            }
        }

        foreach ($products as &$product) {
            $product['addons'] = $addonsByProduct[(int) $product['id']] ?? [];
            $product['image_url'] = ! empty($product['image']) ? media_url((string) $product['image']) : '';
        }
        unset($product);

        return $this->jsonSuccess('Products loaded.', $products);
    }

    public function save(?int $id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
        $model = new ProductModel();

        if ($id && ! $model->find($id)) {
            return $this->jsonError('Product not found.', null, 404);
        }

        $categoryId = filter_var($payload['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || ! (new CategoryModel())->where(['id' => (int) $categoryId, 'is_active' => 1])->first()) {
            return $this->jsonError('Select an active product category.');
        }

        $data = [
            'category_id' => (int) $categoryId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'slug' => url_title((string) ($payload['name'] ?? ''), '-', true),
            'description' => trim((string) ($payload['description'] ?? '')),
            'price' => $payload['price'] ?? '',
            'stock' => $payload['stock'] ?? '',
            'is_available' => $payload['is_available'] ?? 1,
            'is_featured' => $payload['is_featured'] ?? 0,
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
