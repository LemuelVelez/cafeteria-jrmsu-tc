<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sms extends BaseConfig
{
    public bool $enabled;
    public string $provider;
    public string $accountSid;
    public string $authToken;
    public string $fromNumber;
    public string $messagingServiceSid;
    public int $timeout;

    public function __construct()
    {
        parent::__construct();
        $this->enabled = (string) env('SMS_ENABLED', '0') === '1';
        $this->provider = strtolower(trim((string) env('SMS_PROVIDER', 'twilio')));
        $this->accountSid = trim((string) env('TWILIO_ACCOUNT_SID', ''));
        $this->authToken = trim((string) env('TWILIO_AUTH_TOKEN', ''));
        $this->fromNumber = trim((string) env('TWILIO_FROM_NUMBER', ''));
        $this->messagingServiceSid = trim((string) env('TWILIO_MESSAGING_SERVICE_SID', ''));
        $this->timeout = (int) env('SMS_TIMEOUT', 6);
    }
}
