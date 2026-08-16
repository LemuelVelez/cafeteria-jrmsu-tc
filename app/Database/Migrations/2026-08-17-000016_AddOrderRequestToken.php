<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderRequestToken extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('request_token', 'orders')) {
            $this->forge->addColumn('orders', [
                'request_token' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'after' => 'promo_id',
                ],
            ]);
        }

        $this->db->query('CREATE UNIQUE INDEX orders_request_token_unique ON orders (request_token)');
    }

    public function down(): void
    {
        if ($this->db->fieldExists('request_token', 'orders')) {
            $this->db->query('DROP INDEX orders_request_token_unique ON orders');
            $this->forge->dropColumn('orders', 'request_token');
        }
    }
}
