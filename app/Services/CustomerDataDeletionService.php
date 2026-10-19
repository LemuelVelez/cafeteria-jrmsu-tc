<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Anonymize a customer's account and optional PII without deleting financial ledgers. */
final class CustomerDataDeletionService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    public function approve(int $customerId): void
    {
        if ($customerId < 1) {
            throw new \DomainException('Invalid customer.');
        }
        $db = $this->connection ?? db_connect();
        $db->transBegin();
        try {
            $customer = $db->query(
                'SELECT id, avatar, session_version, deletion_requested_at FROM users WHERE id=? AND role=? FOR UPDATE',
                [$customerId, 'customer'],
            )->getRowArray();
            if (! $customer || empty($customer['deletion_requested_at'])) {
                throw new \DomainException('No active customer deletion request.');
            }

            // Preserve the order/item/payment amounts and their identifying row IDs
            // for reconciliation, but remove optional, freely entered personal data.
            $db->query('UPDATE order_items SET notes=NULL WHERE order_id IN (SELECT id FROM orders WHERE customer_id=?)', [$customerId]);
            $db->query('UPDATE order_status_history SET note=NULL WHERE order_id IN (SELECT id FROM orders WHERE customer_id=?)', [$customerId]);
            $db->query('UPDATE order_issues SET details=NULL WHERE order_id IN (SELECT id FROM orders WHERE customer_id=?)', [$customerId]);
            $db->table('orders')->where('customer_id', $customerId)
                ->update(['delivery_address' => null, 'notes' => null]);
            $db->table('reviews')->where('customer_id', $customerId)
                ->update(['comment' => null]);
            // Subscriptions contain encrypted device endpoints; notifications can
            // contain rider names, order instructions, and other unstructured PII.
            $db->table('push_subscriptions')->where('user_id', $customerId)->delete();
            $db->table('notifications')->where('user_id', $customerId)->delete();
            $db->table('user_auth_tokens')->where('user_id', $customerId)->delete();

            $db->table('users')->where('id', $customerId)->update([
                'name' => 'Deleted customer',
                'email' => 'deleted-' . $customerId . '-' . bin2hex(random_bytes(8)) . '@invalid.local',
                'phone' => null, 'address' => null, 'avatar' => null,
                'password_hash' => PasswordHasher::hash(bin2hex(random_bytes(40))),
                'role' => 'customer', 'status' => 'inactive',
                'failed_login_attempts' => 0, 'locked_until' => null,
                'session_version' => (int) $customer['session_version'] + 1,
                'totp_secret' => null, 'totp_recovery_hashes' => null,
                'notification_email_prefs' => null, 'deletion_requested_at' => null,
                'email_verified_at' => null, 'requires_email_verification' => 0,
                'last_login_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new AuditLogService($db))->record('personal_data_deletion_approved', 'user', $customerId,
                ['preserved' => 'financial order history']);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to anonymize customer account.');
            }
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
        // External object stores cannot take part in the database transaction.
        // Delete the now-unreferenced avatar only after the DB operation commits.
        if (! empty($customer['avatar'])) {
            try {
                (new MediaStorageService())->delete((string) $customer['avatar']);
            } catch (\Throwable $error) {
                log_message('warning', 'Deleted customer avatar cleanup failed for account {id}.', ['id' => $customerId]);
            }
        }
    }
}
