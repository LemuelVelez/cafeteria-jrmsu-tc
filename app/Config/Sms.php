<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sms extends BaseConfig
{
    public bool $enabled;
    public string $apiKey;
    public string $endpoint;
    public int $timeout;

    public function __construct()
    {
        parent::__construct();
        $this->enabled = (string) env('SMS_ENABLED', '0') === '1';
        $this->apiKey = trim((string) env('SMSAPIPH_API_KEY', ''));
        $this->endpoint = trim((string) env('SMSAPIPH_ENDPOINT', 'https://smsapiph.onrender.com/api/v1/send/sms'));
        $this->timeout = (int) env('SMS_TIMEOUT', 15);
    }
}
