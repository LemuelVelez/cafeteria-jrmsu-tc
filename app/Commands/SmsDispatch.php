<?php

namespace App\Commands;

use App\Services\SmsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmsDispatch extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'notifications:sms';
    protected $description = 'Send queued order SMS through SMS API PH.';

    public function run(array $params): void
    {
        if (! config(\Config\Sms::class)->enabled) {
            CLI::write('Order SMS is disabled. Set SMS_ENABLED=1 in .env.');
            return;
        }
        $stats = (new SmsService())->flush(5);
        CLI::write('SMS: ' . $stats['sent'] . ' sent; ' . $stats['failed'] . ' failed; ' . $stats['skipped'] . ' skipped; ' . $stats['rate_limited'] . ' rate limited.');
    }
}
