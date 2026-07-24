<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Exceptions extends BaseConfig
{
    public bool $log = true;
    public array $ignoreCodes = [404];
    public string $errorViewPath = APPPATH . 'Views/errors';

    /**
     * Keep this empty to avoid framework trace-masking failures on stack frames
     * that do not contain an arguments list.
     */
    public array $sensitiveDataInTrace = [];

    public bool $logDeprecations = true;
    public string $deprecationLogLevel = LogLevel::WARNING;

    public function handler(int $statusCode, Throwable $exception): ExceptionHandlerInterface
    {
        return new ExceptionHandler($this);
    }
}
