<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Restore a signed local backup into a disposable schema, never into the live database. */
final class BackupRestoreDrillService
{
    /** Tables that must survive backup and restore with identical row counts. */
    public const TABLES = [
        'users', 'categories', 'products', 'product_addons', 'orders',
        'order_items', 'payments', 'order_status_history', 'inventory_movements',
        'ingredients', 'ingredient_movements', 'product_recipes', 'ingredient_cost_history',
        'settings', 'product_allergens', 'order_handoffs',
    ];

    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    public static function scratchDatabaseName(string $source): string
    {
        self::identifier($source);
        $target = $source . '_restore_check';
        self::identifier($target);
        if ($source === $target || str_ends_with($source, '_test')) {
            throw new \DomainException('Restore drill must use a development database, not the test database.');
        }
        return $target;
    }

    /** @param array<string,int> $original @param array<string,int> $restored */
    public static function mismatches(array $original, array $restored): array
    {
        $differences = [];
        foreach (array_unique(array_merge(array_keys($original), array_keys($restored))) as $table) {
            if (! array_key_exists($table, $original) || ! array_key_exists($table, $restored)
                || $original[$table] !== $restored[$table]) {
                $differences[$table] = [
                    'source' => $original[$table] ?? null,
                    'restored' => $restored[$table] ?? null,
                ];
            }
        }
        return $differences;
    }

    /**
     * Creates a new backup, verifies its checksum and imports it into an ABSENT scratch DB.
     * The scratch schema is dropped in finally, including if the import or comparison fails.
     * Live orders/stock must be quiescent for row-count comparisons to be meaningful.
     *
     * @return array{backup:string,source:string,scratch:string,counts:array<string,int>}
     */
    public function run(): array
    {
        $db = $this->connection ?? db_connect();
        $db->initialize();
        if ((string) $db->DBDriver !== 'MySQLi') {
            throw new \RuntimeException('Backup restore drill requires MySQLi.');
        }
        $source = (string) $db->database;
        $scratch = self::scratchDatabaseName($source);
        $exists = $db->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$scratch]
        )->getRowArray();
        if ($exists) {
            throw new \DomainException('Refusing to overwrite existing scratch database ' . $scratch . '.');
        }
        $original = $this->counts($db, $source);
        $backup = (new DatabaseBackupService())->create();
        $path = DatabaseBackupService::DIRECTORY . $backup;
        if (! DatabaseBackupService::checksum($path)) {
            throw new \RuntimeException('Backup checksum failed before restoring.');
        }

        $created = false;
        try {
            $db->query('CREATE DATABASE ' . self::identifier($scratch)
                . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created = true;
            $this->import($db, $scratch, $path);
            $restored = $this->counts($db, $scratch);
            $differences = self::mismatches($original, $restored);
            if ($differences !== []) {
                throw new \RuntimeException('Backup/restore row counts differ: ' . json_encode($differences, JSON_THROW_ON_ERROR));
            }
            (new AuditLogService($db))->record('database_restore_drill', 'backup', null,
                ['file' => $backup, 'tables_checked' => count($restored), 'result' => 'passed']);
            return ['backup' => $backup, 'source' => $source, 'scratch' => $scratch, 'counts' => $restored];
        } finally {
            if ($created) {
                // Only drop databases created by this invocation, never an existing schema.
                $db->query('DROP DATABASE ' . self::identifier($scratch));
            }
        }
    }

    private static function identifier(string $name): string
    {
        if (strlen($name) > 64 || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $name)) {
            throw new \DomainException('Unsafe or overlong MySQL database identifier.');
        }
        return '`' . $name . '`';
    }

    /** @return array<string,int> */
    private function counts(BaseConnection $db, string $schema): array
    {
        $qualifiedSchema = self::identifier($schema);
        $result = [];
        foreach (self::TABLES as $table) {
            $result[$table] = (int) $db->query(
                'SELECT COUNT(*) AS total FROM ' . $qualifiedSchema . '.' . self::identifier($table)
            )->getRow('total');
        }
        return $result;
    }

    private function import(BaseConnection $db, string $scratch, string $path): void
    {
        $mysql = trim((string) @shell_exec('command -v mysql 2>/dev/null'));
        if ($mysql === '' || ! is_executable($mysql)) {
            throw new \RuntimeException('mysql client is required for backup/restore verification.');
        }
        $credentials = tempnam(sys_get_temp_dir(), 'cafeteria-mysql-');
        $stderr = tempnam(sys_get_temp_dir(), 'cafeteria-mysql-error-');
        if ($credentials === false || $stderr === false) {
            throw new \RuntimeException('Unable to create temporary restore drill files.');
        }
        $input = null;
        $process = null;
        try {
            @chmod($credentials, 0600);
            if (file_put_contents($credentials, DatabaseBackupService::clientOptions($db)) === false) {
                throw new \RuntimeException('Cannot write database credentials.');
            }
            // Command arguments are an array, so no credentials/identifier pass through a shell.
            $process = proc_open(
                [$mysql, '--defaults-extra-file=' . $credentials, '--binary-mode', $scratch],
                [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $stderr, 'w']],
                $pipes
            );
            if (! is_resource($process)) {
                throw new \RuntimeException('Could not start mysql restore client.');
            }
            $input = gzopen($path, 'rb');
            if ($input === false) {
                throw new \RuntimeException('Cannot open compressed backup.');
            }
            while (! gzeof($input)) {
                $chunk = gzread($input, 32768);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read compressed backup.');
                }
                $pending = strlen($chunk);
                $offset = 0;
                while ($offset < $pending) {
                    $written = @fwrite($pipes[0], substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException('mysql restore client closed its input unexpectedly.');
                    }
                    $offset += $written;
                }
            }
            gzclose($input);
            $input = null;
            fclose($pipes[0]);
            $exit = proc_close($process);
            $process = null;
            if ($exit !== 0) {
                throw new \RuntimeException('Restore drill import failed: ' . mb_substr((string) file_get_contents($stderr), 0, 250));
            }
        } finally {
            if (is_resource($input)) {
                gzclose($input);
            }
            if (is_resource($process)) {
                if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
                proc_close($process);
            }
            @unlink($credentials);
            @unlink($stderr);
        }
    }
}
