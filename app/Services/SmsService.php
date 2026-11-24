<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Throwable;

class SmsService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    public function enqueue(int $userId, ?int $orderId, string $event, string $message): void
    {
        // Coming soon: never queue SMS until explicitly released via SMS_ENABLED.
        if (! config(\Config\Sms::class)->enabled) return;
        $db = $this->db();
        $enabled = $db->table('settings')->where('setting_key', 'sms_order_notifications')->get()->getRow('setting_value');
        if ((string) ($enabled ?? '0') !== '1') return;
        // Enqueued inside the calling order transaction; the unique key prevents duplicate events.
        $db->table('sms_outbox')->ignore(true)->insert([
            'user_id' => $userId, 'order_id' => $orderId,
            'event' => mb_substr($event, 0, 40), 'message' => mb_substr($message, 0, 480),
            'status' => 'queued', 'attempts' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function flush(int $limit = 5): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'rate_limited' => 0];
        // Preserve any existing queued rows while SMS is disabled.
        if (! config(\Config\Sms::class)->enabled) return $stats;
        $db = $this->db();
        // Prevent overlapping HTTP senders, without holding a database transaction over the request.
        $lock = (int) $db->query("SELECT GET_LOCK('cafeteria.sms-dispatch',0) held")->getRow('held');
        if ($lock !== 1) return $stats;
        try {
            $rows = $db->table('sms_outbox')->where('status', 'queued')->where('attempts <', 3)
                ->orderBy('id', 'ASC')->limit(max(1, min(100, $limit)))->get()->getResultArray();
            $config = config(\Config\Sms::class);
            foreach ($rows as $index => $row) {
                $sendAttempted = false;
                $outcome = 'failed';
                $changes = [];
                try {
                    $user = $db->table('users')->select('phone')->where('id', (int) $row['user_id'])->get()->getRowArray();
                    $phone = self::normalizePhone(FieldEncryption::open($user['phone'] ?? null));
                    if ($phone === null) {
                        $outcome = 'skipped';
                        $changes['last_error'] = 'No valid PH mobile number';
                    } elseif ($config->apiKey === '') {
                        $outcome = 'skipped';
                        $changes['last_error'] = 'SMS API PH API key not configured';
                    } else {
                        $sendAttempted = true;
                        $result = $this->sendViaSmsApiPh($row, $phone, $config);
                        $outcome = $result['outcome'];
                        $changes['last_error'] = $result['error'];
                        if ($outcome === 'sent') {
                            // The gateway accepted the message; final handset delivery is not guaranteed.
                            $changes['provider_message_id'] = $result['provider_message_id'];
                            $changes['sent_at'] = date('Y-m-d H:i:s');
                        }
                    }
                } catch (Throwable $exception) {
                    // Never store provider credentials, phone numbers, or response bodies in error logs.
                    $changes['last_error'] = 'SMS dispatch failed';
                    log_message('error', 'SMS dispatch failed for outbox row {id}', ['id' => (int) $row['id']]);
                }

                try {
                    $db->transBegin();
                    $locked = $db->query('SELECT id,status,attempts FROM sms_outbox WHERE id=? FOR UPDATE', [(int) $row['id']])->getRowArray();
                    if ($locked && $locked['status'] === 'queued') {
                        $attempts = (int) $locked['attempts'] + (in_array($outcome, ['skipped', 'rate_limited'], true) ? 0 : 1);
                        $changes['attempts'] = $attempts;
                        $changes['status'] = $outcome === 'sent' ? 'sent' : ($outcome === 'skipped' ? 'skipped' : ($outcome !== 'rate_limited' && $attempts >= 3 ? 'failed' : 'queued'));
                        $db->table('sms_outbox')->where('id', $locked['id'])->update($changes);
                        if (! $db->transStatus()) throw new \RuntimeException('Could not update SMS outbox row.');
                        $db->transCommit();
                        $stats[$outcome]++;
                    } else {
                        $db->transCommit();
                    }
                } catch (Throwable $exception) {
                    $db->transRollback();
                    $stats['failed']++;
                    log_message('error', 'SMS outbox update failed for row {id}', ['id' => (int) $row['id']]);
                }

                if ($outcome === 'rate_limited') break;
                if ($sendAttempted && $index < count($rows) - 1 && is_cli()) sleep(10);
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK('cafeteria.sms-dispatch')");
        }
        return $stats;
    }

    private function sendViaSmsApiPh(array $row, string $phone, \Config\Sms $config): array
    {
        $response = service('curlrequest')->post($config->endpoint, [
            'headers' => [
                'x-api-key' => $config->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'recipient' => '+63' . substr($phone, 1),
                'message' => $row['message'],
            ],
            'timeout' => $config->timeout,
            'http_errors' => false,
        ]);
        $httpCode = $response->getStatusCode();
        if ($httpCode === 429) {
            return ['outcome' => 'rate_limited', 'provider_message_id' => null, 'error' => 'Rate limited by SMS API PH; will retry'];
        }
        $payload = json_decode((string) $response->getBody(), true);
        if ($httpCode < 200 || $httpCode >= 300 || ! is_array($payload) || ($payload['success'] ?? null) === false) {
            return ['outcome' => 'failed', 'provider_message_id' => null, 'error' => 'SMS API PH rejected the request (HTTP ' . $httpCode . ')'];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = null;
        foreach ([$data['id'] ?? null, $data['messageId'] ?? null, $data['message_id'] ?? null,
            $payload['id'] ?? null, $payload['messageId'] ?? null, $payload['message_id'] ?? null] as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                $messageId = mb_substr((string) $candidate, 0, 64);
                break;
            }
        }
        return ['outcome' => 'sent', 'provider_message_id' => $messageId, 'error' => null];
    }

    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) return null;
        $phone = preg_replace('/[\s.()\-]+/u', '', $raw);
        if (preg_match('/^09\d{9}$/', $phone)) return $phone;
        if (preg_match('/^\+?639\d{9}$/', $phone)) return '0' . substr(ltrim($phone, '+'), 2);
        return null;
    }

    public static function flushQuietly(): void
    {
        try {
            (new self())->flush(1);
        } catch (Throwable $exception) {
            log_message('error', 'SMS dispatch unavailable: {class}', ['class' => get_class($exception)]);
        }
    }
}
