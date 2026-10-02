<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class MenuController extends BaseController
{
    public function show(string $slug): string
    {
        $product = (new ProductModel())->where('slug', $slug)->first();
        if (! $product) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->render('public/menu/show', [
            'title' => $product['name'],
            'product' => $product,
            'addons' => (new ProductAddonModel())->where(['product_id' => $product['id'], 'is_active' => 1])->findAll(),
        ]);
    }
}
