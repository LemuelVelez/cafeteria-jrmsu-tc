<?php

namespace App\Models;

use Throwable;

class SettingModel extends BaseModel
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['setting_key', 'setting_value', 'setting_type', 'description'];

    public function getValue(string $key, mixed $default = null): mixed
    {
        try {
            $row = $this->where('setting_key', $key)->first();

            return $row['setting_value'] ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }

    /** @return array<string, mixed> */
    public function getValues(array $keys, mixed $default = null): array
    {
        $values = array_fill_keys($keys, $default);

        try {
            $rows = $this->whereIn('setting_key', $keys)->findAll();
            foreach ($rows as $row) {
                $values[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            // Defaults keep the application usable before the settings table exists.
        }

        return $values;
    }

    public function setValue(string $key, string $value): bool
    {
        $existing = $this->where('setting_key', $key)->first();

        return $existing
            ? $this->update($existing['id'], ['setting_value' => $value])
            : (bool) $this->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => 'string',
            ]);
    }
}
