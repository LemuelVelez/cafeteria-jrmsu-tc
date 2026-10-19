<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Intentionally constructs a strict public projection, never serializes DB rows. */
final class QueueFeedService
{
    public function __construct(private readonly ?BaseConnection $connection = null) {}

    public static function present(array $orders, bool $sound, bool $voice, ?\DateTimeImmutable $now = null, int $hideMinutes = 30): array
    {
        $zone = new \DateTimeZone(config(\Config\Cafeteria::class)->timezone);
        $now ??= new \DateTimeImmutable('now', $zone);
        $today = $now->format('Y-m-d');
        $preparing = [];
        $ready = [];
        foreach ($orders as $order) {
            if (($order['queue_date'] ?? '') !== $today || ($order['order_type'] ?? '') !== 'pickup' || empty($order['queue_number'])) continue;
            $label = format_queue_number((int) $order['queue_number']);
            if (in_array($order['status'] ?? '', ['confirmed','preparing'], true)) {
                $preparing[] = $label;
            } elseif (($order['status'] ?? '') === 'ready_for_pickup' && ! empty($order['ready_at'])) {
                $readyAt = new \DateTimeImmutable((string) $order['ready_at'], $zone);
                if ($readyAt > $now || $readyAt < $now->modify('-' . $hideMinutes . ' minutes')) continue;
                $calledAt = ! empty($order['queue_called_at'])
                    ? new \DateTimeImmutable((string) $order['queue_called_at'], $zone) : $readyAt;
                $ready[] = ['number' => $label, 'called_at' => $calledAt->format(DATE_ATOM), 'calls' => (int) ($order['queue_call_count'] ?? 0)];
            }
        }
        usort($ready, static fn (array $a, array $b): int => strcmp($b['called_at'], $a['called_at']));
        return ['generated_at' => $now->format(DATE_ATOM), 'preparing' => $preparing,
            'ready' => $ready, 'sound' => $sound, 'voice' => $voice];
    }

    public function feed(int $hideMinutes, bool $sound, bool $voice): array
    {
        $zone = new \DateTimeZone(config(\Config\Cafeteria::class)->timezone);
        $now = new \DateTimeImmutable('now', $zone);
        $db = $this->connection ?? db_connect();
        $rows = $db->table('orders')->select('queue_number,queue_date,order_type,status,ready_at,queue_called_at,queue_call_count')
            ->where('queue_date', $now->format('Y-m-d'))->where('order_type', 'pickup')
            ->whereIn('status', ['confirmed','preparing','ready_for_pickup'])
            ->where('queue_number IS NOT NULL', null, false)
            ->orderBy('queue_number', 'ASC')->get()->getResultArray();
        return self::present($rows, $sound, $voice, $now, $hideMinutes);
    }
}
