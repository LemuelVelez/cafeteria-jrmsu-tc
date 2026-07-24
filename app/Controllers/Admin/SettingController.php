<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class SettingController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/settings/index', ['settings' => array_column((new SettingModel())->findAll(), 'setting_value', 'setting_key')]);
    }

    public function save()
    {
        $model = new SettingModel();
        foreach (['cafeteria_name', 'delivery_fee', 'operating_hours', 'contact_number', 'pickup_enabled', 'delivery_enabled'] as $key) {
            $model->setValue($key, trim((string) $this->request->getPost($key)));
        }
        return redirect()->back()->with('success', 'Settings updated.');
    }
}
