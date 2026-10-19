<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Services\AuditLogService;

final class QueueController extends BaseController
{
    public function call(int $id)
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $order = $db->query('SELECT id,order_type,status,queue_call_count FROM orders WHERE id=? FOR UPDATE', [$id])->getRowArray();
            if (! $order || $order['order_type'] !== 'pickup' || $order['status'] !== 'ready_for_pickup') {
                throw new \DomainException('Only ready pickup orders can be called.');
            }
            if (! service('throttler')->check('queue-call-' . $id, 1, 15)) {
                $db->transRollback();
                return $this->respond(false, 'Please allow 15 seconds before calling this order again.', 429);
            }
            $time = (new \DateTimeImmutable('now', new \DateTimeZone(config(\Config\Cafeteria::class)->timezone)))->format('Y-m-d H:i:s');
            $db->table('orders')->where('id', $id)->update([
                'queue_called_at' => $time, 'queue_call_count' => min(255, (int) $order['queue_call_count'] + 1),
            ]);
            (new AuditLogService($db))->record('queue_called_again', 'order', $id,
                ['queue_call_count' => min(255, (int) $order['queue_call_count'] + 1)]);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to call the queue number.');
            $db->transCommit();
            return $this->respond(true, 'Queue number called again.');
        } catch (\Throwable $error) {
            $db->transRollback();
            return $this->respond(false, $error instanceof \DomainException ? $error->getMessage() : 'Unable to call the queue number.', 422);
        }
    }

    private function respond(bool $success, string $message, int $status = 200)
    {
        if ($this->request->isAJAX() || str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->response->setStatusCode($status)->setJSON(['success' => $success, 'message' => $message]);
        }
        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }
}
