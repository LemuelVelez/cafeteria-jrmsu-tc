<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Models\ProductAddonModel;
use App\Models\ProductModel;
use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class MenuController extends BaseController
{
    public function index(): string
    {
        $products=(new ProductModel())->select('products.*,categories.name category_name')->join('categories','categories.id=products.category_id')->where('categories.is_active',1)->where('categories.deleted_at',null)->orderBy('categories.sort_order')->orderBy('products.name')->findAll();
        return $this->render('public/menu/index', ['title'=>'Public menu','products'=>$products,'categories'=>(new CategoryModel())->where('is_active',1)->findAll()]);
    }

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
