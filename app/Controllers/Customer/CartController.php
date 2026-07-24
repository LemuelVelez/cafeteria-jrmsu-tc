<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class CartController extends BaseController
{
    public function index(): string
    {
        $productImages = [];
        $products = (new ProductModel())
            ->select('id, image')
            ->findAll();

        foreach ($products as $product) {
            $productImages[(string) $product['id']] = ! empty($product['image'])
                ? media_url($product['image'])
                : base_url('assets/img/jrmsu-cafeteria-logo.png');
        }

        return $this->render('customer/cart/index', [
            'productImages' => $productImages,
        ]);
    }
}
