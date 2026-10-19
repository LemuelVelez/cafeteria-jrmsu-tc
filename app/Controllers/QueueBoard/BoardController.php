<?php

namespace App\Controllers\QueueBoard;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\QueueFeedService;

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
        if (! service('throttler')->check('queue-board-' . hash('sha256', $this->request->getIPAddress()), 120, MINUTE)) {
            return $this->response->setHeader('Cache-Control', 'no-store')->setStatusCode(429)->setJSON(['error' => 'Too many requests']);
        }
        $settings = new SettingModel();
        $hide = max(5, min(240, (int) $settings->getValue('queue_ready_hide_minutes', '30')));
        $sound = (string) $settings->getValue('queue_sound_enabled', '1') === '1';
        $voice = (string) $settings->getValue('queue_voice_enabled', '1') === '1';
        return $this->response->setHeader('Cache-Control', 'no-store')
            ->setJSON((new QueueFeedService())->feed($hide, $sound, $voice));
    }
}
