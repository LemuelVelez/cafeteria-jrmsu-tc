<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    public function __construct(private readonly UserModel $users = new UserModel())
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (! $user || $user['status'] !== 'active' || ! password_verify($password, $user['password_hash'])) {
            return false;
        }

        session()->regenerate(true);
        session()->set('user', [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'avatar' => $user['avatar'] ?? null,
        ]);
        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    public function registerCustomer(array $data): int|false
    {
        return $this->users->insert([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim($data['phone'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'avatar' => $data['avatar'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'customer',
            'status' => 'active',
        ], true);
    }

    public function logout(): void
    {
        session()->destroy();
    }
}
