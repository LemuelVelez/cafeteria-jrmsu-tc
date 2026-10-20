<?php

namespace App\Controllers\QueueBoard;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\QueueFeedService;
use Throwable;

final class BoardController extends BaseController
{
    private function enabled(): bool
    {
        return (string) (new SettingModel())->getValue('queue_board_enabled', '1') === '1';
    }

    public function index()
    {
        if (! $this->enabled()) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return view('queue_board/index', [
            'cafeteriaName' => (string) (new SettingModel())->getValue('cafeteria_name', config(\Config\Cafeteria::class)->name),
        ]);
    }

    public function feed()
    {
        if (! $this->enabled()) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        try {
            // Cache-backed rate limiting is best effort for this read-only public screen.
            try {
                if (! service('throttler')->check('queue-board-' . hash('sha256', $this->request->getIPAddress()), 120, MINUTE)) {
                    return $this->response->setHeader('Cache-Control', 'no-store')
                        ->setHeader('Retry-After', '60')->setStatusCode(429)
                        ->setJSON(['error' => 'Too many requests', 'code' => 'rate_limited']);
                }
            } catch (Throwable $throttleFailure) {
                log_message('warning', '[QueueBoard] Throttler storage unavailable: {detail}', [
                    'detail' => self::describe($throttleFailure),
                ]);
            }

            $settings = new SettingModel();
            $hide = max(5, min(240, (int) $settings->getValue('queue_ready_hide_minutes', '30')));
            $sound = (string) $settings->getValue('queue_sound_enabled', '1') === '1';
            $voice = (string) $settings->getValue('queue_voice_enabled', '1') === '1';
            $feed = (new QueueFeedService())->feed($hide, $sound, $voice);

            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON($feed);
        } catch (Throwable $exception) {
            // Do not expose driver errors, file locations, or SQL to unauthenticated clients.
            log_message('error', '[QueueBoard] Feed failed: {detail}', ['detail' => self::describe($exception)]);
            $missingSchema = false;
            $dbUnreachable = false;
            try {
                $db = db_connect();
                // Query rather than just connect: some drivers connect lazily.
                $db->query('SELECT 1');
            } catch (Throwable $databaseFailure) {
                $dbUnreachable = true;
                log_message('critical', '[QueueBoard] database unreachable: {detail}', [
                    'detail' => self::describe($databaseFailure),
                ]);
            }
            if (! $dbUnreachable) {
                try {
                    foreach (['queue_number', 'queue_date', 'ready_at', 'queue_called_at', 'queue_call_count'] as $field) {
                        if (! $db->fieldExists($field, 'orders')) $missingSchema = true;
                    }
                    if (! $db->tableExists('queue_counters')) $missingSchema = true;
                } catch (Throwable $schemaFailure) {
                    log_message('error', '[QueueBoard] Queue schema check failed: {detail}', [
                        'detail' => self::describe($schemaFailure),
                    ]);
                }
            }
            if ($missingSchema) {
                log_message('critical', '[QueueBoard] Queue schema incomplete; migrate the deployed database (php spark migrate --all).');
            }
            return $this->response->setHeader('Cache-Control', 'no-store')
                ->setHeader('Retry-After', $missingSchema ? '300' : '60')
                ->setStatusCode(503)->setJSON([
                    'error' => $missingSchema
                        ? 'Queue setup is incomplete. Please contact an administrator.'
                        : 'Queue updates are temporarily unavailable.',
                    'code' => $missingSchema ? 'queue_setup_required' : 'queue_unavailable',
                ]);
        }
    }

    private static function describe(Throwable $exception): string
    {
        return get_class($exception) . ' at ' . $exception->getFile() . ':' . $exception->getLine()
            . ' — ' . $exception->getMessage();
    }
}
