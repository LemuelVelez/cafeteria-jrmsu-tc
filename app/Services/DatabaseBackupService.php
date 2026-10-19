<?php

namespace App\Services;

final class DatabaseBackupService
{
    public const DIRECTORY = WRITEPATH . 'backups/';

    public static function checksum(string $path): bool
    {
        return is_file($path)
            && is_file($path . '.sha256')
            && hash_equals(trim((string) file_get_contents($path . '.sha256')), (string) hash_file('sha256', $path));
    }

    public function create(): string
    {
        $db = db_connect();
        $db->initialize();
        if (! is_dir(self::DIRECTORY) && ! @mkdir(self::DIRECTORY, 0700, true) && ! is_dir(self::DIRECTORY)) {
            throw new \RuntimeException('Cannot create the private backup directory.');
        }
        if (! is_writable(self::DIRECTORY)) {
            throw new \RuntimeException('The private backup directory is not writable.');
        }
        if (! function_exists('gzopen')) {
            throw new \RuntimeException('The PHP zlib extension is required for backups.');
        }

        $name = 'cafeteria-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql.gz';
        $path = self::DIRECTORY . $name;
        $partial = $path . '.partial';

        try {
            $this->createArchive($db, $partial);
            if (! is_file($partial) || filesize($partial) < 15) {
                throw new \RuntimeException('The database export is incomplete.');
            }
            $hash = hash_file('sha256', $partial);
            if ($hash === false || ! @rename($partial, $path)) {
                throw new \RuntimeException('Could not save the database backup.');
            }
            if (file_put_contents($path . '.sha256.partial', $hash, LOCK_EX) !== strlen($hash)
                || ! @rename($path . '.sha256.partial', $path . '.sha256')) {
                throw new \RuntimeException('Could not write the backup checksum.');
            }
            @chmod($path, 0600);
            @chmod($path . '.sha256', 0600);
        } catch (\Throwable $e) {
            @unlink($partial);
            @unlink($path);
            @unlink($path . '.sha256');
            @unlink($path . '.sha256.partial');
            throw $e;
        }

        // A successful local backup remains valid even when optional S3 upload
        // or retention cleanup fails. Record the staff action either way.
        (new AuditLogService())->record('database_backup', 'backup', null, [
            'file' => $name,
            'sha256' => (string) hash_file('sha256', $path),
        ]);
        try {
            $this->retention();
        } catch (\Throwable $e) {
            log_message('error', 'Backup retention cleanup failed: {message}', ['message' => $e->getMessage()]);
        }
        try {
            $this->uploadS3($path);
        } catch (\Throwable $e) {
            log_message('error', 'Optional offsite backup upload failed: {message}', ['message' => $e->getMessage()]);
        }

        return $name;
    }

    /**
     * PHP installations commonly disable shell_exec/proc_open, or do not ship
     * mysqldump. Prefer the full native export when usable, but always retry
     * from a clean gzip file with the PHP exporter when it is not.
     */
    private function createArchive(object $db, string $path): void
    {
        $binary = self::findDumpExecutable();
        if ($binary !== null && function_exists('proc_open')) {
            try {
                $stream = @gzopen($path, 'wb9');
                if ($stream === false) {
                    throw new \RuntimeException('Cannot open the compressed backup file.');
                }
                try {
                    $this->dumpProcess($binary, $db, $stream);
                } finally {
                    gzclose($stream);
                }
                return;
            } catch (\Throwable $e) {
                @unlink($path); // Never append PHP SQL to a partial mysqldump.
                log_message('warning', 'Native database backup unavailable; using PHP export ({reason}).', [
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $stream = @gzopen($path, 'wb9');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open the compressed backup file.');
        }
        try {
            $this->dumpPhp($db, $stream);
        } finally {
            gzclose($stream);
        }
    }

    /** Find executables without shell_exec(), which may be disabled. */
    public static function findDumpExecutable(?string $path = null): ?string
    {
        $path ??= (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            foreach (['mysqldump', 'mariadb-dump'] as $name) {
                $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if (is_file($file) && is_executable($file)) {
                    return $file;
                }
            }
        }
        return null;
    }

    /** Quote MySQL option-file values without JSON-style slash escaping. */
    public static function clientOptions(object $db): string
    {
        $quote = static function (string $value): string {
            return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value) . '"';
        };
        return "[client]\n"
            . 'user=' . $quote((string) $db->username) . "\n"
            . 'password=' . $quote((string) $db->password) . "\n"
            . 'host=' . $quote((string) $db->hostname) . "\n"
            . 'port=' . (int) $db->port . "\n";
    }

    private function dumpProcess(string $binary, object $db, $output): void
    {
        $credentials = tempnam(sys_get_temp_dir(), 'cafe-credentials-');
        $errors = tempnam(sys_get_temp_dir(), 'cafe-dump-errors-');
        if ($credentials === false || $errors === false) {
            throw new \RuntimeException('Cannot create the temporary database dump files.');
        }
        try {
            @chmod($credentials, 0600);
            @chmod($errors, 0600);
            if (file_put_contents($credentials, self::clientOptions($db)) === false) {
                throw new \RuntimeException('Cannot write temporary database credentials.');
            }
            $cmd = [$binary, '--defaults-extra-file=' . $credentials, '--single-transaction',
                '--routines', '--triggers', '--default-character-set=utf8mb4', '--no-tablespaces', (string) $db->database];
            // Write stderr directly to a file: a full stderr pipe can deadlock
            // a process whose stdout is being consumed at the same time.
            $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes);
            if (! is_resource($process)) {
                throw new \RuntimeException('Cannot run the database dump tool.');
            }
            fclose($pipes[0]);
            try {
                while (! feof($pipes[1])) {
                    $part = fread($pipes[1], 32768);
                    if ($part === false) {
                        throw new \RuntimeException('Unable to read the native database export.');
                    }
                    if ($part !== '') {
                        self::writeGzip($output, $part);
                    }
                }
            } finally {
                fclose($pipes[1]);
                $exit = proc_close($process);
            }
            // Do not log stderr: some clients echo connection details.
            if ($exit !== 0) {
                throw new \RuntimeException('The native dump tool exited with code ' . $exit . '.');
            }
        } finally {
            @unlink($credentials);
            @unlink($errors);
        }
    }

    private static function writeGzip($stream, string $sql): void
    {
        $length = strlen($sql);
        for ($offset = 0; $offset < $length;) {
            $written = gzwrite($stream, substr($sql, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Cannot write the compressed backup (disk may be full).');
            }
            $offset += $written;
        }
    }

    /** Portable fallback: streams table data in bounded pages instead of loading all rows. */
    private function dumpPhp(object $db, $output): void
    {
        self::writeGzip($output, "-- Portable MySQL table backup (no stored routines or triggers).\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        $tables = $db->query('SHOW FULL TABLES')->getResultArray();
        foreach ($tables as $table) {
            $name = (string) reset($table);
            if (strtoupper((string) (array_values($table)[1] ?? '')) !== 'BASE TABLE') {
                continue;
            }
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
                throw new \RuntimeException('Unexpected database table name.');
            }
            $structure = $db->query('SHOW CREATE TABLE `' . $name . '`')->getRowArray();
            $sql = (string) ($structure['Create Table'] ?? '');
            if ($sql === '') {
                throw new \RuntimeException('Cannot read the structure of table ' . $name . '.');
            }
            self::writeGzip($output, 'DROP TABLE IF EXISTS `' . $name . "`;\n" . $sql . ";\n");
            $offset = 0;
            $batchSize = 200;
            do {
                $rows = $db->query('SELECT * FROM `' . $name . '` LIMIT ' . $batchSize . ' OFFSET ' . $offset)->getResultArray();
                foreach ($rows as $row) {
                    $values = array_map(static fn ($v) => $db->escape($v), array_values($row));
                    self::writeGzip($output, 'INSERT INTO `' . $name . '` (`'
                        . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', $values) . ");\n");
                }
                $count = count($rows);
                $offset += $count;
            } while ($count === $batchSize);
        }
        self::writeGzip($output, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    public function files(): array
    {
        if (! is_dir(self::DIRECTORY)) {
            return [];
        }
        $names = array_filter(array_map('basename', glob(self::DIRECTORY . 'cafeteria-*.sql.gz') ?: []));
        rsort($names);
        return array_map(static fn ($name) => [
            'name' => $name,
            'size' => filesize(self::DIRECTORY . $name),
            'valid' => self::checksum(self::DIRECTORY . $name),
        ], $names);
    }

    /** @param list<string> $filenames @return list<string> */
    public static function retainedNames(array $filenames, int $daily, int $weekly, int $monthly): array
    {
        $files = array_values(array_unique($filenames));
        rsort($files, SORT_STRING);
        $selected = [];
        $days = [];
        $weeks = [];
        $months = [];
        $metadata = [];
        foreach ($files as $name) {
            if (! preg_match('/^cafeteria-(\d{8})-(\d{6})-[a-f0-9]{6}\.sql\.gz$/D', $name, $matches)) continue;
            $date = \DateTimeImmutable::createFromFormat('!Ymd-His', $matches[1] . '-' . $matches[2]);
            $errors = \DateTimeImmutable::getLastErrors();
            if (! $date || ($errors !== false && ($errors['error_count'] || $errors['warning_count']))) continue;
            $metadata[$name] = [
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('o-W'),
                'month' => $date->format('Y-m'),
            ];
        }
        foreach (['day' => max(1, $daily), 'week' => max(0, $weekly), 'month' => max(0, $monthly)] as $period => $limit) {
            $picked = 0;
            foreach ($metadata as $name => $date) {
                if ($picked >= $limit) break;
                if (isset($selected[$name]) || isset($days[$date['day']])) continue;
                if ($period !== 'day' && isset($weeks[$date['week']])) continue;
                if ($period === 'month' && isset($months[$date['month']])) continue;
                $selected[$name] = true;
                $days[$date['day']] = true;
                $weeks[$date['week']] = true;
                $months[$date['month']] = true;
                $picked++;
            }
        }
        return array_keys($selected);
    }

    public function retention(): void
    {
        $keepDaily = max(1, (int) $this->setting('backup_keep_daily', '7'));
        $keepWeekly = max(0, (int) $this->setting('backup_keep_weekly', '4'));
        $keepMonthly = max(0, (int) $this->setting('backup_keep_monthly', '6'));
        $files = $this->files();
        $keep = self::retainedNames(array_column($files, 'name'), $keepDaily, $keepWeekly, $keepMonthly);
        foreach ($files as $file) {
            if (! in_array($file['name'], $keep, true)) {
                @unlink(self::DIRECTORY . $file['name']);
                @unlink(self::DIRECTORY . $file['name'] . '.sha256');
            }
        }
    }

    private function setting(string $key, string $fallback): string
    {
        return (string) (db_connect()->table('settings')->where('setting_key', $key)->get()->getRow('setting_value') ?? $fallback);
    }

    private function uploadS3(string $path): void
    {
        $bucket = (string) env('AWS_S3_BUCKET', '');
        if ($bucket === '') return;
        if (! class_exists('Aws\\S3\\S3Client')) {
            throw new \RuntimeException('The AWS SDK is unavailable.');
        }
        $client = new \Aws\S3\S3Client([
            'version' => 'latest', 'region' => (string) env('AWS_REGION', 'ap-southeast-1'),
        ]);
        $client->putObject([
            'Bucket' => $bucket,
            'Key' => 'cafeteria-backups/' . basename($path),
            'Body' => fopen($path, 'rb'),
            'ServerSideEncryption' => 'AES256',
        ]);
    }
}
