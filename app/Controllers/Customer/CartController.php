<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class CartController extends BaseController
{
    public function index(): string
    {
        $productImages = [];
        $productImageFallbacks = [];
        $products = (new ProductModel())
            ->select('products.id, products.image, products.slug, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->findAll();

        foreach ($products as $product) {
            $image = product_image($product);
            $productImages[(string) $product['id']] = $image['url'];
            $productImageFallbacks[(string) $product['id']] = $image['fallback'];
        }

        return $this->render('customer/cart/index', [
            'productImages' => $productImages,
            'productImageFallbacks' => $productImageFallbacks,
        ]);
    }
}
