<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    public array $default = [
        'DSN'      => '',
        'hostname' => '127.0.0.1',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'MySQLi',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8mb4',
        'DBCollat' => 'utf8mb4_unicode_ci',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => true,
        'failover' => [],
        'port'     => 3306,
        'numberNative' => false,
        'foundRows' => false,
        'dateFormat' => [
            'date' => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time' => 'H:i:s',
        ],
    ];

    public array $tests = [
        'DSN' => '',
        'hostname' => '127.0.0.1',
        'username' => '',
        'password' => '',
        'database' => ':memory:',
        'DBDriver' => 'SQLite3',
        'DBPrefix' => 'db_',
        'pConnect' => false,
        'DBDebug' => true,
        'charset' => 'utf8',
        'DBCollat' => '',
        'swapPre' => '',
        'encrypt' => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port' => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat' => [
            'date' => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time' => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
            return;
        }

        $databaseUrl = getenv('MySQL_Database_URL');
        if (is_string($databaseUrl) && $databaseUrl !== '') {
            $connection = parse_url($databaseUrl);

            if (is_array($connection)) {
                $this->default['DSN'] = '';
                $this->default['DBDriver'] = 'MySQLi';
                $this->default['hostname'] = rawurldecode($connection['host'] ?? '127.0.0.1');
                $this->default['username'] = rawurldecode($connection['user'] ?? '');
                $this->default['password'] = rawurldecode($connection['pass'] ?? '');
                $this->default['database'] = rawurldecode(ltrim($connection['path'] ?? '', '/'));
                $this->default['port'] = isset($connection['port']) ? (int) $connection['port'] : 3306;
                $this->default['charset'] = 'utf8mb4';
                $this->default['DBCollat'] = 'utf8mb4_unicode_ci';
                $this->default['strictOn'] = true;
                $this->default['DBDebug'] = ENVIRONMENT !== 'production';
            }
        }
    }
}
