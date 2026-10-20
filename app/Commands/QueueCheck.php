<?php

namespace App\Commands;

use App\Services\QueueFeedService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

final class QueueCheck extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:check';
    protected $description = 'Diagnose the deployed queue board database, cache, timezone, and feed.';

    public function run(array $params): void
    {
        $db = null;
        $this->check('Database connection', static function () use (&$db): void {
            $db = db_connect();
            $db->query('SELECT 1');
        });
        $this->check('Five queue columns', static function () use (&$db): void {
            if ($db === null) throw new \RuntimeException('Database connection failed.');
            foreach (['queue_number', 'queue_date', 'ready_at', 'queue_called_at', 'queue_call_count'] as $field) {
                if (! $db->fieldExists($field, 'orders')) throw new \RuntimeException('Missing orders.' . $field);
            }
        });
        $this->check('queue_counters table', static function () use (&$db): void {
            if ($db === null) throw new \RuntimeException('Database connection failed.');
            if (! $db->tableExists('queue_counters')) throw new \RuntimeException('Missing queue_counters table.');
        });
        $this->check('writable/cache storage', static function (): void {
            $directory = WRITEPATH . 'cache';
            if (! is_dir($directory) || ! is_writable($directory)) throw new \RuntimeException('Not writable: ' . $directory);
            $name = tempnam($directory, 'queue_check_');
            if ($name === false) throw new \RuntimeException('Cannot create a test file in ' . $directory);
            try {
                if (file_put_contents($name, 'ok') === false) throw new \RuntimeException('Cannot write a test file.');
            } finally {
                @unlink($name);
            }
        });
        $this->check('CAFETERIA_TIMEZONE', static function (): void {
            $name = config(\Config\Cafeteria::class)->timezone;
            new \DateTimeZone($name);
        });
        $this->check('QueueFeedService::feed()', static function () use (&$db): void {
            if ($db === null) throw new \RuntimeException('Database connection failed.');
            (new QueueFeedService($db))->feed(30, true, true);
        });
    }

    private function check(string $label, callable $run): void
    {
        try {
            $run();
            CLI::write('PASS ' . $label, 'green');
        } catch (Throwable $exception) {
            CLI::write('FAIL ' . $label . ': ' . get_class($exception) . ': ' . $exception->getMessage(), 'red');
        }
    }
}
