<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = password_hash('Cafeteria#2026Demo', PASSWORD_DEFAULT);
        $rows = [
            ['name' => 'System Administrator', 'email' => 'admin@jrmsu.edu.ph', 'phone' => '09170000001', 'password_hash' => $password, 'role' => 'admin', 'status' => 'active', 'address' => null],
            ['name' => 'Main Cashier', 'email' => 'cashier@jrmsu.edu.ph', 'phone' => '09170000002', 'password_hash' => $password, 'role' => 'cashier', 'status' => 'active', 'address' => null],
            ['name' => 'Campus Rider', 'email' => 'rider@jrmsu.edu.ph', 'phone' => '09170000003', 'password_hash' => $password, 'role' => 'rider', 'status' => 'active', 'address' => null],
            ['name' => 'Student Customer', 'email' => 'customer@jrmsu.edu.ph', 'phone' => '09170000004', 'password_hash' => $password, 'role' => 'customer', 'status' => 'active', 'address' => 'JRMSU-TC Campus, Tampilisan'],
        ];
        $now = date('Y-m-d H:i:s');
        $users = $this->db->table('users');

        foreach ($rows as $row) {
            if ($users->where('email', $row['email'])->countAllResults() > 0) {
                continue;
            }

            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $users->insert($row);
        }
    }
}
