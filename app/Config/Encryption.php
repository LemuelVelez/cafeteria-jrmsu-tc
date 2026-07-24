<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Encryption extends BaseConfig
{
    public string $key = '';
    public string $driver = 'OpenSSL';
    public int $blockSize = 16;
    public string $digest = 'SHA512';
}
