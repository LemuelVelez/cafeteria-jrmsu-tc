<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cafeteria extends BaseConfig
{
    public string $name;
    public string $currency;
    public string $timezone;
    public float $deliveryFee;
    public string $orderPrefix;
    public int $uploadMaxSizeMb;

    public function __construct()
    {
        parent::__construct();
        $this->name = (string) env('CAFETERIA_NAME', 'JRMSU-TC Cafeteria');
        $this->currency = (string) env('CAFETERIA_CURRENCY', 'PHP');
        $this->timezone = (string) env('CAFETERIA_TIMEZONE', 'Asia/Manila');
        $this->deliveryFee = (float) env('CAFETERIA_DELIVERY_FEE', 40);
        $this->orderPrefix = (string) env('CAFETERIA_ORDER_PREFIX', 'JRMSU');
        $this->uploadMaxSizeMb = (int) env('UPLOAD_MAX_SIZE_MB', 5);
    }
}
