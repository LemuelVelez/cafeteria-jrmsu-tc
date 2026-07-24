<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;

class MenuController extends BaseController
{
    public function index(): string
    {
        $categoryId = $this->request->getGet('category') ? (int) $this->request->getGet('category') : null;
        $search = trim((string) $this->request->getGet('q')) ?: null;
        $products = (new ProductModel())->menu($categoryId, $search);
        $addons = [];
        if ($products) {
            $ids = array_column($products, 'id');
            foreach ((new ProductAddonModel())->whereIn('product_id', $ids)->where('is_active', 1)->findAll() as $addon) {
                $addons[$addon['product_id']][] = $addon;
            }
        }
        return $this->render('customer/menu/index', [
            'products' => $products,
            'categories' => (new CategoryModel())->where('is_active', 1)->orderBy('sort_order')->findAll(),
            'addons' => $addons,
            'selectedCategory' => $categoryId,
            'search' => $search,
        ]);
    }
}
