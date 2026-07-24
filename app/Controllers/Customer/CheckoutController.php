<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class CheckoutController extends BaseController
{
    public function index(): string
    {
        $settings = (new SettingModel())->getValues([
            'delivery_fee',
            'pickup_enabled',
            'delivery_enabled',
        ]);

        return $this->render('customer/checkout/index', [
            'deliveryFee' => (float) ($settings['delivery_fee'] ?? env('CAFETERIA_DELIVERY_FEE', 40)),
            'pickupEnabled' => (string) ($settings['pickup_enabled'] ?? '1') === '1',
            'deliveryEnabled' => (string) ($settings['delivery_enabled'] ?? '1') === '1',
        ]);
    }
}
