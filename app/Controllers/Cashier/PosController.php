<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\UserModel;

class PosController extends BaseController
{
    public function index(): string
    {
        return $this->render('cashier/pos/index', [
            'products' => (new ProductModel())->menu(),
            'categories' => (new CategoryModel())->where('is_active', 1)->orderBy('sort_order')->findAll(),
            'customers' => (new UserModel())->where(['role' => 'customer', 'status' => 'active'])->orderBy('name')->findAll(),
        ]);
    }
}
