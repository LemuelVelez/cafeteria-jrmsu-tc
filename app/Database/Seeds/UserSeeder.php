<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = password_hash('Password123!', PASSWORD_DEFAULT);
        $rows = [
            ['name' => 'System Administrator', 'email' => 'admin@jrmsu.edu.ph', 'phone' => '09170000001', 'password_hash' => $password, 'role' => 'admin', 'status' => 'active'],
            ['name' => 'Main Cashier', 'email' => 'cashier@jrmsu.edu.ph', 'phone' => '09170000002', 'password_hash' => $password, 'role' => 'cashier', 'status' => 'active'],
            ['name' => 'Campus Rider', 'email' => 'rider@jrmsu.edu.ph', 'phone' => '09170000003', 'password_hash' => $password, 'role' => 'rider', 'status' => 'active'],
            ['name' => 'Student Customer', 'email' => 'customer@jrmsu.edu.ph', 'phone' => '09170000004', 'password_hash' => $password, 'role' => 'customer', 'status' => 'active', 'address' => 'JRMSU-TC Campus, Tampilisan'],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        $this->db->table('users')->insertBatch($rows);
    }
}
