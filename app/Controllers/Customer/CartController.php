<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;

class CartController extends BaseController
{
    public function index(): string
    {
        return $this->render('customer/cart/index');
    }
}
