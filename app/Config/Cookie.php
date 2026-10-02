<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use DateTimeInterface;

class Cookie extends BaseConfig
{
    public string $prefix = '';
    public DateTimeInterface|int|string $expires = 0;
    public string $path = '/';
    public string $domain = '';
    public bool $secure;
    public bool $httponly = true;
    public string $samesite = 'Lax';
    public bool $raw = false;

    public function __construct()
    {
        parent::__construct();
        $this->secure = filter_var(env('app.cookieSecure', defined('ENVIRONMENT') && ENVIRONMENT === 'production'), FILTER_VALIDATE_BOOL);
    }
}

