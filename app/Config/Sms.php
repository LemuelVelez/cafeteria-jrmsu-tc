<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sms extends BaseConfig
{
    public string $provider;
    public string $apiKey;
    public string $senderName;
    public string $endpoint;
    public int $timeout;

    public function __construct()
    {
        parent::__construct();
        $this->provider = (string) env('SMS_PROVIDER', 'semaphore');
        $this->apiKey = (string) env('SEMAPHORE_API_KEY', '');
        $this->senderName = (string) env('SEMAPHORE_SENDER_NAME', '');
        $this->endpoint = (string) env('SEMAPHORE_ENDPOINT', 'https://api.semaphore.co/api/v4/messages');
        $this->timeout = (int) env('SMS_TIMEOUT', 6);
    }
}
