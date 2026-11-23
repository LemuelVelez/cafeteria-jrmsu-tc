<?php

namespace App\Commands;

use App\Services\SmsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmsDispatch extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'notifications:sms';
    protected $description = 'Send queued order SMS outside order transactions.';

    public function run(array $params): void
    {
        $stats = (new SmsService())->flush(50);
        CLI::write('SMS: ' . $stats['sent'] . ' sent; ' . $stats['failed'] . ' failed; ' . $stats['skipped'] . ' skipped.');
    }
}
