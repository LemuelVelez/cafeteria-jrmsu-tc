<?php

namespace App\Models;

class UserModel extends BaseModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'email', 'phone', 'password_hash', 'role', 'status', 'address', 'avatar', 'last_login_at', 'requires_email_verification', 'email_verified_at'];
    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'email' => 'required|valid_email|max_length[160]',
        'role' => 'required|in_list[admin,cashier,rider,customer]',
        'status' => 'permit_empty|in_list[active,inactive,banned]',
        'avatar' => 'permit_empty|max_length[500]',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    public function activeRiders(): array
    {
        return $this->where(['role' => 'rider', 'status' => 'active'])->orderBy('name')->findAll();
    }
}
