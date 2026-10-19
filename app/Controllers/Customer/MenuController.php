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
        $under500 = $this->request->getGet('under_500') === '1';
        $excludeAllergen = trim((string) $this->request->getGet('exclude_allergen')) ?: null;
        $filters=[];
        foreach(['high_protein','low_sodium','healthy_choice','vegetarian'] as $key) $filters[$key]=$this->request->getGet($key)==='1';
        $products = (new ProductModel())->menu($categoryId, $search, $under500 ? 500 : null, $excludeAllergen, $filters);
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
            'allergenOptions' => (new \App\Services\AllergenService())->options(),
            'selectedCategory' => $categoryId,
            'search' => $search,
            'under500' => $under500,
            'filters' => $filters,
            'excludeAllergen' => $excludeAllergen,
        ]);
    }
}
