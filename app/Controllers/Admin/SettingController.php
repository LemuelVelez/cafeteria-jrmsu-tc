<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\AuditLogService;
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
        'email_order_notifications',
        'admin_totp_required',
        'attendance_enabled',
        'backup_keep_daily', 'backup_keep_weekly', 'backup_keep_monthly',
        'attendance_overtime_premium', 'attendance_night_differential',
        'notification_read_retention_days', 'inventory_ingredient_tracking_enabled', 'low_stock_email_digest', 'web_push_enabled',
        'queue_board_enabled', 'queue_sound_enabled', 'queue_voice_enabled', 'queue_ready_hide_minutes', 'queue_number_prefix',
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
            'email_order_notifications' => 'required|in_list[0,1]',
            'admin_totp_required' => 'required|in_list[0,1]',
            'attendance_enabled' => 'required|in_list[0,1]',
            'backup_keep_daily' => 'required|is_natural_no_zero|less_than_equal_to[365]',
            'backup_keep_weekly' => 'required|is_natural|less_than_equal_to[52]',
            'backup_keep_monthly' => 'required|is_natural|less_than_equal_to[120]',
            'attendance_overtime_premium' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[3]',
            'attendance_night_differential' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[3]',
            'notification_read_retention_days' => 'required|is_natural_no_zero|less_than_equal_to[3650]',
            'inventory_ingredient_tracking_enabled' => 'required|in_list[0,1]',
            'low_stock_email_digest' => 'required|in_list[0,1]',
            'web_push_enabled' => 'required|in_list[0,1]',
            'queue_board_enabled' => 'required|in_list[0,1]',
            'queue_sound_enabled' => 'required|in_list[0,1]',
            'queue_voice_enabled' => 'required|in_list[0,1]',
            'queue_ready_hide_minutes' => 'required|integer|greater_than_equal_to[5]|less_than_equal_to[240]',
            'queue_number_prefix' => 'permit_empty|max_length[3]|regex_match[/^[A-Za-z0-9]{0,3}$/]',
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

            (new AuditLogService($database))->record('settings_change', 'settings', null, ['keys' => self::KEYS]);
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
