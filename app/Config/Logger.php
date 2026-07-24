<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\FileHandler;
use Psr\Log\LogLevel;

class Logger extends BaseConfig
{
    public int|array $threshold = ENVIRONMENT === 'production' ? 4 : 9;
    public string $dateFormat = 'Y-m-d H:i:s';
    public array $handlers = [
        FileHandler::class => [
            'handles' => [LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY, LogLevel::DEBUG, LogLevel::ERROR, LogLevel::INFO, LogLevel::NOTICE, LogLevel::WARNING],
            'fileExtension' => '',
            'filePermissions' => 0644,
            'path' => '',
        ],
    ];
}
