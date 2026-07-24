<?php

namespace App\Models;

class SettingModel extends BaseModel
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['setting_key', 'setting_value', 'setting_type', 'description'];

    public function setValue(string $key, string $value): bool
    {
        $existing = $this->where('setting_key', $key)->first();
        return $existing ? $this->update($existing['id'], ['setting_value' => $value]) : (bool) $this->insert(['setting_key' => $key, 'setting_value' => $value, 'setting_type' => 'string']);
    }
}
