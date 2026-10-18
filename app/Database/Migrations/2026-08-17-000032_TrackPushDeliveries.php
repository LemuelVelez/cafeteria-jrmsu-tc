<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Track acknowledged deliveries individually so retrying one device never repeats another. */
final class TrackPushDeliveries extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'notification_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'subscription_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'delivered_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey(['notification_id', 'subscription_id'], true);
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subscription_id', 'push_subscriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_delivery_receipts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('push_delivery_receipts', true);
    }
}
