<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use App\Services\MediaStorageService;
use Throwable;

class ProductApiController extends BaseController
{
    public function index()
    {
        $products = (new ProductModel())->menu(
            $this->request->getGet('category') ? (int) $this->request->getGet('category') : null,
            trim((string) $this->request->getGet('q')) ?: null,
            $this->request->getGet('under_500') ? 500 : null,
            trim((string) $this->request->getGet('exclude_allergen')) ?: null,
        );
        $this->attachAddons($products);
        return $this->jsonSuccess('Products loaded.', $products);
    }

    public function barcode(string $barcode)
    {
        $product = (new ProductModel())->where('barcode', $barcode)->first();
        if (! $product) {
            return $this->jsonError('Product not found.', null, 404);
        }
        $product['addons'] = (new ProductAddonModel())->where(['product_id' => $product['id'], 'is_active' => 1])->findAll();
        $product['image_url'] = ! empty($product['image']) ? media_url((string) $product['image']) : '';
        $product['available'] = (bool) $product['is_available'] && (int) $product['stock'] > 0;
        return $this->jsonSuccess('Product loaded.', $product);
    }

    public function save(?int $id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
        $model = new ProductModel();
        $existing = $id ? $model->find($id) : null;
        if ($id && ! $existing) {
            return $this->jsonError('Product not found.', null, 404);
        }
        $categoryId = filter_var($payload['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || ! (new CategoryModel())->where(['id' => (int) $categoryId, 'is_active' => 1])->first()) {
            return $this->jsonError('Select an active product category.');
        }

        $sku = trim((string) ($payload['sku'] ?? '')) ?: null;
        $barcode = trim((string) ($payload['barcode'] ?? '')) ?: null;
        foreach (['sku' => $sku, 'barcode' => $barcode] as $field => $value) {
            if ($value !== null) {
                $owner = (new ProductModel())->withDeleted()->where($field, $value)->first();
                if ($owner && (int) $owner['id'] !== (int) ($id ?? 0)) {
                    return $this->jsonError(ucfirst($field) . ' is already assigned to another product.');
                }
            }
        }

        $data = [
            'category_id' => (int) $categoryId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'slug' => url_title((string) ($payload['name'] ?? ''), '-', true),
            'sku' => $sku,
            'barcode' => $barcode,
            'description' => trim((string) ($payload['description'] ?? '')),
            'price' => $payload['price'] ?? '',
            'reorder_level' => $payload['reorder_level'] ?? 5,
            'serving_size' => $this->nullable($payload['serving_size'] ?? null),
            'calories' => $this->nullable($payload['calories'] ?? null),
            'protein_g' => $this->nullable($payload['protein_g'] ?? null),
            'carbohydrates_g' => $this->nullable($payload['carbohydrates_g'] ?? null),
            'fat_g' => $this->nullable($payload['fat_g'] ?? null),
            'sugar_g' => $this->nullable($payload['sugar_g'] ?? null),
            'fiber_g' => $this->nullable($payload['fiber_g'] ?? null),
            'sodium_mg' => $this->nullable($payload['sodium_mg'] ?? null),
            'allergens' => $this->nullable($payload['allergens'] ?? null),
            'is_healthy_choice' => ! empty($payload['is_healthy_choice']) ? 1 : 0,
            'is_available' => ! empty($payload['is_available']) ? 1 : 0,
            'is_featured' => ! empty($payload['is_featured']) ? 1 : 0,
        ];
        $initialStock = null;
        if (! $id) {
            $initialStock = filter_var($payload['stock'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($initialStock === false) {
                return $this->jsonError('Initial stock must be zero or greater.');
            }
            $data['stock'] = (int) $initialStock;
        }

        $db = db_connect();
        $db->transBegin();
        try {
            if ($id) {
                $db->query('SELECT id FROM products WHERE id=? FOR UPDATE', [$id]);
                $ok = $model->update($id, $data);
                $productId = $id;
            } else {
                $ok = $model->insert($data, true);
                $productId = (int) $ok;
            }
            if (! $ok) {
                throw new \DomainException(implode(' ', $model->errors()) ?: 'Product validation failed.');
            }
            if (! $id) {
                (new InventoryService($db))->opening($productId, (int) $initialStock, (int) (session()->get('user')['id'] ?? 0));
            }
            $saved = $model->find($productId);
            (new AuditLogService($db))->record($id ? 'product_updated' : 'product_created', 'product', $productId, ['old_price' => $existing['price'] ?? null, 'new_price' => $saved['price'] ?? null]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to save product.');
            }
            $db->transCommit();
            return $this->jsonSuccess('Product saved.', $saved, $id ? 200 : 201);
        } catch (\DomainException $exception) {
            $db->transRollback();
            return $this->jsonError($exception->getMessage(), $model->errors());
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'API product save failed: {message}', ['message' => $exception->getMessage()]);
            return $this->jsonError('The product could not be saved.', null, 500);
        }
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

    private function attachAddons(array &$products): void
    {
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
    }

    private function nullable(mixed $value): mixed
    {
        return $value === null || trim((string) $value) === '' ? null : $value;
    }
}
