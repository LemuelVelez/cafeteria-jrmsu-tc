<?php

namespace App\Models;

use App\Services\FieldEncryption;

class UserModel extends BaseModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'email', 'phone', 'password_hash', 'role', 'status', 'address', 'avatar', 'last_login_at', 'requires_email_verification', 'email_verified_at', 'failed_login_attempts', 'locked_until', 'session_version', 'privacy_consent_at', 'deletion_requested_at', 'totp_secret', 'totp_recovery_hashes'];
    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'email' => 'required|valid_email|max_length[160]',
        'phone' => 'permit_empty|max_length[30]',
        'address' => 'permit_empty|max_length[1000]',
        'role' => 'required|in_list[admin,cashier,rider,customer]',
        'status' => 'permit_empty|in_list[active,inactive,banned]',
        'avatar' => 'permit_empty|max_length[500]',
    ];

    protected $beforeInsert=['sealFields'];
    protected $beforeUpdate=['sealFields'];
    protected $afterFind=['openFields'];

    protected function sealFields(array $event): array
    {
        foreach (['phone','address','totp_secret'] as $field) {
            if (array_key_exists($field,$event['data'])) $event['data'][$field]=FieldEncryption::seal($event['data'][$field]);
        }
        return $event;
    }
    protected function openFields(array $event): array
    {
        if (!isset($event['data']))return $event;
        $decode=static function(array $row): array {
            foreach(['phone','address','totp_secret'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = $field === 'totp_secret'
                        ? FieldEncryption::openTotp((string) $row[$field])
                        : FieldEncryption::openForDisplay((string) $row[$field]);
                }
            }
            return $row;
        };
        if (is_array($event['data'])) {
            $event['data'] = array_is_list($event['data'])
                ? array_map($decode, $event['data'])
                : $decode($event['data']);
        }
        return $event;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    public function activeRiders(): array
    {
        return $this->where(['role' => 'rider', 'status' => 'active'])->orderBy('name')->findAll();
    }
}
