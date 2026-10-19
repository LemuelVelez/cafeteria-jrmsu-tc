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

        $databaseUrl = getenv('MySQL_Database_URL');
        if (is_string($databaseUrl) && $databaseUrl !== '') {
            $connection = parse_url($databaseUrl);

            if (is_array($connection) && ! empty($connection['path'])) {
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

        if (ENVIRONMENT === 'testing') {
            // Unit tests must still bootstrap if MySQL is not configured. DB tests skip
            // explicitly; they must never silently run MySQL-specific SQL on SQLite.
            $this->tests = $this->default;
            $this->tests['database'] = empty($this->default['database'])
                ? '' : $this->default['database'] . '_test';
            $this->tests['DBDriver'] = 'MySQLi';
            $this->tests['DBPrefix'] = '';
            $this->tests['pConnect'] = false;
            if ($this->tests['database'] !== '' && $this->tests['database'] === $this->default['database']) {
                throw new \RuntimeException('Test and development database must differ.');
            }
            $this->defaultGroup = 'tests';
            $this->default = $this->tests;
        }
    }
}
