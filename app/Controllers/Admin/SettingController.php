<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use Throwable;

class SettingController extends BaseController
{
    private const KEYS = [
        'cafeteria_name',
        'delivery_fee',
        'operating_hours',
        'contact_number',
        'pickup_enabled',
        'delivery_enabled',
    ];

    public function index(): string
    {
        return $this->render('admin/settings/index', [
            'settings' => (new SettingModel())->getValues(self::KEYS, ''),
        ]);
    }

    public function save()
    {
        if (! $this->validate([
            'cafeteria_name' => 'required|min_length[2]|max_length[120]',
            'delivery_fee' => 'required|decimal|greater_than_equal_to[0]',
            'operating_hours' => 'permit_empty|max_length[160]',
            'contact_number' => 'permit_empty|max_length[50]',
            'pickup_enabled' => 'required|in_list[0,1]',
            'delivery_enabled' => 'required|in_list[0,1]',
        ])) {
            return redirect()->to('/admin/settings')
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $model = new SettingModel();
        $database = db_connect();
        $database->transBegin();

        try {
            foreach (self::KEYS as $key) {
                if (! $model->setValue($key, trim((string) $this->request->getPost($key)))) {
                    throw new \RuntimeException('Unable to save the cafeteria settings.');
                }
            }

            if (! $database->transStatus()) {
                throw new \RuntimeException('Unable to save the cafeteria settings.');
            }

            $database->transCommit();

            return redirect()->to('/admin/settings')->with('success', 'Settings updated.');
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Cafeteria settings update failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->to('/admin/settings')
                ->withInput()
                ->with('error', 'The settings could not be updated.');
        }
    }
}
