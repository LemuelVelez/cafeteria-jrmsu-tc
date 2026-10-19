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
            if (! service('throttler')->check('queue-board-' . hash('sha256', $this->request->getIPAddress()), 120, MINUTE)) {
                return $this->response->setHeader('Cache-Control', 'no-store')
                    ->setHeader('Retry-After', '60')->setStatusCode(429)
                    ->setJSON(['error' => 'Too many requests', 'code' => 'rate_limited']);
            }

            $settings = new SettingModel();
            $hide = max(5, min(240, (int) $settings->getValue('queue_ready_hide_minutes', '30')));
            $sound = (string) $settings->getValue('queue_sound_enabled', '1') === '1';
            $voice = (string) $settings->getValue('queue_voice_enabled', '1') === '1';
            $feed = (new QueueFeedService())->feed($hide, $sound, $voice);

            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON($feed);
        } catch (Throwable $exception) {
            // Never send SQL/exception details to a public, unauthenticated display.
            // Inspect the schema only on failures, not on every six-second poll.
            $missingSchema = false;
            try {
                $db = db_connect();
                foreach (['queue_number', 'queue_date', 'ready_at', 'queue_called_at', 'queue_call_count'] as $field) {
                    if (! $db->fieldExists($field, 'orders')) {
                        $missingSchema = true;
                        break;
                    }
                }
                if (! $db->tableExists('queue_counters')) $missingSchema = true;
            } catch (Throwable) {
                // An inaccessible database cannot be diagnosed with a schema query.
            }

            log_message('error', '[QueueBoard] Feed failed: {message}', ['message' => $exception->getMessage()]);
            if ($missingSchema) {
                log_message('critical', '[QueueBoard] Queue schema is incomplete. Run php spark migrate --all on the deployed application.');
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
}
