<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\ReviewModel;

class Home extends BaseController
{
    public function index(): string
    {
        $products = [];
        $categories = [];
        $reviews = [];
        try {
            $products = array_slice((new ProductModel())->menu(), 0, 6);
            $categories = (new CategoryModel())->where('is_active', 1)->orderBy('sort_order')->findAll();
            $reviews = (new ReviewModel())->select('reviews.*, users.name AS customer_name')->join('users', 'users.id = reviews.customer_id')->where('is_visible', 1)->orderBy('reviews.created_at', 'DESC')->findAll(3);
        } catch (\Throwable) {
            // The landing page remains available before the first migration.
        }
        return $this->render('home', compact('products', 'categories', 'reviews'));
    }
}
