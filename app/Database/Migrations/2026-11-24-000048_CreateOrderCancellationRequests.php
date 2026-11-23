<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderCancellationRequests extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE order_cancellation_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL,
            review_note VARCHAR(255) NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_order_cancellation_order (order_id),
            KEY idx_order_cancellation_status (status),
            CONSTRAINT fk_cancel_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_cancel_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_cancel_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->forge->dropTable('order_cancellation_requests', true);
    }
}
