<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Models\SettingModel;
use App\Models\UserModel;

class PosController extends BaseController
{
    public function index(): string
    {
        $settings = (new SettingModel())->getValues([
            'delivery_fee',
            'pickup_enabled',
            'delivery_enabled',
        ]);

        $products = (new ProductModel())->menu();
        $addons = [];
        if ($products !== []) {
            foreach ((new ProductAddonModel())->whereIn('product_id', array_column($products, 'id'))->where('is_active', 1)->findAll() as $addon) {
                $addons[(int) $addon['product_id']][] = $addon;
            }
        }

        return $this->render('cashier/pos/index', [
            'products' => $products,
            'addons' => $addons,
            'categories' => (new CategoryModel())->where('is_active', 1)->orderBy('sort_order')->findAll(),
            'customers' => (new UserModel())->where(['role' => 'customer', 'status' => 'active'])->orderBy('name')->findAll(),
            'deliveryFee' => max(0.0, (float) ($settings['delivery_fee'] ?? env('CAFETERIA_DELIVERY_FEE', 40))),
            'pickupEnabled' => (string) ($settings['pickup_enabled'] ?? '1') === '1',
            'deliveryEnabled' => (string) ($settings['delivery_enabled'] ?? '1') === '1',
        ]);
    }
}
