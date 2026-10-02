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
    /** @var array<string,int> */
    public array $idleTimeouts;

    public function __construct()
    {
        parent::__construct();
        $this->name = (string) env('CAFETERIA_NAME', 'JRMSU-TC Cafeteria');
        $this->currency = (string) env('CAFETERIA_CURRENCY', 'PHP');
        $this->timezone = (string) env('CAFETERIA_TIMEZONE', 'Asia/Manila');
        $this->deliveryFee = (float) env('CAFETERIA_DELIVERY_FEE', 40.00);
        $this->orderPrefix = (string) env('CAFETERIA_ORDER_PREFIX', 'JRMSU');
        $this->uploadMaxSizeMb = (int) env('UPLOAD_MAX_SIZE_MB', 5);
        $defaultIdle = (int) env('CAFETERIA_IDLE_TIMEOUT', 1800);
        $this->idleTimeouts = [
            'admin' => (int) env('CAFETERIA_IDLE_TIMEOUT_ADMIN', $defaultIdle),
            'cashier' => (int) env('CAFETERIA_IDLE_TIMEOUT_CASHIER', $defaultIdle),
            'rider' => (int) env('CAFETERIA_IDLE_TIMEOUT_RIDER', $defaultIdle),
            'customer' => (int) env('CAFETERIA_IDLE_TIMEOUT_CUSTOMER', 7200),
        ];
    }
}
