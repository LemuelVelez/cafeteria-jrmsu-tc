<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;

class CheckoutController extends BaseController
{
    public function index(): string
    {
        return $this->render('customer/checkout/index', ['deliveryFee' => (float) env('CAFETERIA_DELIVERY_FEE', 40)]);
    }
}
