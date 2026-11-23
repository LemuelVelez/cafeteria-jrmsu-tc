<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

class OrderCancellationService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    public function eligibility(array $order, int $customerId): array
    {
        $window = config(\Config\Cafeteria::class)->cancellationRequestWindowSeconds;
        $elapsed = max(0, time() - strtotime((string) ($order['created_at'] ?? '')));
        $left = max(0, $window - $elapsed);
        if ((int) ($order['customer_id'] ?? 0) !== $customerId) {
            return ['eligible' => false, 'seconds_left' => 0, 'reason' => 'This is not your order.'];
        }
        if (! empty($order['cashier_id'])) {
            return ['eligible' => false, 'seconds_left' => 0, 'reason' => 'Cancellation requests are for online orders only.'];
        }
        if (! in_array($order['status'] ?? '', ['pending', 'confirmed'], true)) {
            return ['eligible' => false, 'seconds_left' => 0, 'reason' => 'This order is already being prepared or has progressed beyond cancellation requests.'];
        }
        if ($this->forOrder((int) $order['id']) !== null) {
            return ['eligible' => false, 'seconds_left' => 0, 'reason' => 'A cancellation request already exists for this order.'];
        }
        if ($elapsed > $window) {
            return ['eligible' => false, 'seconds_left' => 0, 'reason' => 'The 2-minute cancellation window has ended.'];
        }
        return ['eligible' => true, 'seconds_left' => $left, 'reason' => ''];
    }

    public function request(int $orderId, int $customerId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
            throw new DomainException('Please provide a cancellation reason between 5 and 255 characters.');
        }
        $db = $this->db();
        $db->transBegin();
        try {
            $order = $db->query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$orderId])->getRowArray();
            if (! $order) throw new DomainException('Order not found.');
            $eligibility = $this->eligibility($order, $customerId);
            if (! $eligibility['eligible']) throw new DomainException($eligibility['reason']);
            $now = date('Y-m-d H:i:s');
            if (! $db->table('order_cancellation_requests')->insert([
                'order_id' => $orderId, 'customer_id' => $customerId, 'reason' => $reason,
                'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
            ])) throw new \RuntimeException('Unable to save the cancellation request.');
            $id = (int) $db->insertID();
            (new AuditLogService($db))->record('order_cancellation_requested', 'order', $orderId, ['request_id' => $id], $customerId);
            (new NotificationService($db))->cancellationRequested($order, $reason);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to save the cancellation request.');
            $db->transCommit();
            return $this->forOrder($orderId) ?? ['id' => $id, 'order_id' => $orderId, 'status' => 'pending'];
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function approve(int $requestId, array $actor, ?string $note = null): void
    {
        $this->requireReviewer($actor);
        $db = $this->db();
        $db->transBegin();
        try {
            $request = $db->query('SELECT * FROM order_cancellation_requests WHERE id=? FOR UPDATE', [$requestId])->getRowArray();
            if (! $request || $request['status'] !== 'pending') throw new DomainException('Cancellation request is no longer pending.');
            $now = date('Y-m-d H:i:s');
            $db->table('order_cancellation_requests')->where('id', $requestId)->update([
                'status' => 'approved', 'reviewed_by' => (int) $actor['id'], 'reviewed_at' => $now,
                'review_note' => $note === null ? null : mb_substr(trim($note), 0, 255), 'updated_at' => $now,
            ]);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to approve the cancellation request.');
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        // OrderService owns its own order transaction; do not nest it inside the request transaction.
        try {
            (new OrderService())->updateStatus((int) $request['order_id'], 'cancelled', $actor,
                'Customer cancellation request approved: ' . $request['reason']);
        } catch (Throwable $exception) {
            $db->transBegin();
            try {
                $locked = $db->query('SELECT * FROM order_cancellation_requests WHERE id=? FOR UPDATE', [$requestId])->getRowArray();
                if ($locked && $locked['status'] === 'approved') {
                    $db->table('order_cancellation_requests')->where('id', $requestId)->update([
                        'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null,
                        'review_note' => null, 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $db->transCommit();
            } catch (Throwable $restoreException) {
                $db->transRollback();
                log_message('error', 'Cancellation approval reset failed: {message}', ['message' => $restoreException->getMessage()]);
            }
            throw $exception;
        }
        $db->transBegin();
        try {
            $db->query('SELECT id FROM order_cancellation_requests WHERE id=? FOR UPDATE', [$requestId]);
            (new AuditLogService($db))->record('order_cancellation_approved', 'order', (int) $request['order_id'], ['request_id' => $requestId], (int) $actor['id']);
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function reject(int $requestId, array $actor, string $note): void
    {
        $this->requireReviewer($actor);
        $note = trim($note);
        if (mb_strlen($note) < 3 || mb_strlen($note) > 255) throw new DomainException('Please enter a rejection note between 3 and 255 characters.');
        $db = $this->db();
        $db->transBegin();
        try {
            $request = $db->query('SELECT * FROM order_cancellation_requests WHERE id=? FOR UPDATE', [$requestId])->getRowArray();
            if (! $request || $request['status'] !== 'pending') throw new DomainException('Cancellation request is no longer pending.');
            $order = $db->query('SELECT * FROM orders WHERE id=? FOR UPDATE', [(int) $request['order_id']])->getRowArray();
            if (! $order) throw new DomainException('Order not found.');
            $now = date('Y-m-d H:i:s');
            $db->table('order_cancellation_requests')->where('id', $requestId)->update([
                'status' => 'rejected', 'reviewed_by' => (int) $actor['id'], 'reviewed_at' => $now,
                'review_note' => $note, 'updated_at' => $now,
            ]);
            (new AuditLogService($db))->record('order_cancellation_rejected', 'order', (int) $request['order_id'], ['request_id' => $requestId], (int) $actor['id']);
            (new NotificationService($db))->cancellationRejected($order, $note);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to reject the cancellation request.');
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function pendingByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) return [];
        $rows = $this->db()->table('order_cancellation_requests')->whereIn('order_id', array_map('intval', $orderIds))
            ->where('status', 'pending')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['order_id']] = $row;
        return $result;
    }

    public function forOrder(int $orderId): ?array
    {
        return $this->db()->table('order_cancellation_requests')->where('order_id', $orderId)->get()->getRowArray();
    }

    private function requireReviewer(array $actor): void
    {
        if (! in_array($actor['role'] ?? '', ['admin', 'cashier'], true) || (int) ($actor['id'] ?? 0) < 1) {
            throw new DomainException('Only a cashier or administrator may review cancellation requests.');
        }
    }
}
