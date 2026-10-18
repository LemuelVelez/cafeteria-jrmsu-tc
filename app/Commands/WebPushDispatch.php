<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\WebPushService;
class WebPushDispatch extends BaseCommand
{
    protected $group='Notifications';
    protected $name='notifications:push';
    protected $description='Send queued Web Push notifications outside order transactions.';
    public function run(array $params): void
    {
        $stats=(new WebPushService())->flush();
        CLI::write('Push notifications: '.$stats['sent'].' sent; '.$stats['failed'].' need retry.');
    }
}
