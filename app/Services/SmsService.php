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
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        // While the feature is marked coming soon, preserve any existing queued rows.
        if (! config(\Config\Sms::class)->enabled) return $stats;
        $db = $this->db();
        // Prevent overlapping HTTP senders, without holding a database transaction over the request.
        $lock = (int) $db->query("SELECT GET_LOCK('cafeteria.sms-dispatch',0) held")->getRow('held');
        if ($lock !== 1) return $stats;
        try {
            $rows = $db->table('sms_outbox')->where('status', 'queued')->where('attempts <', 3)
                ->orderBy('id', 'ASC')->limit(max(1, min(100, $limit)))->get()->getResultArray();
            $config = config(\Config\Sms::class);
            foreach ($rows as $row) {
                $outcome = 'failed';
                $changes = [];
                try {
                    $user = $db->table('users')->select('phone')->where('id', (int) $row['user_id'])->get()->getRowArray();
                    $phone = self::normalizePhone(FieldEncryption::open($user['phone'] ?? null));
                    if ($phone === null) {
                        $outcome = 'skipped';
                        $changes['last_error'] = 'No valid PH mobile number';
                    } elseif ($config->provider !== 'twilio'
                        || $config->accountSid === '' || $config->authToken === ''
                        || ($config->fromNumber === '' && $config->messagingServiceSid === '')) {
                        $outcome = 'skipped';
                        $changes['last_error'] = 'SMS provider not configured';
                    } else {
                        // Twilio requires an E.164 recipient, not the locally stored 09XXXXXXXXX.
                        $form = [
                            'To' => '+63' . substr($phone, 1),
                            'Body' => $row['message'],
                        ];
                        if ($config->messagingServiceSid !== '') {
                            $form['MessagingServiceSid'] = $config->messagingServiceSid;
                        } else {
                            $form['From'] = $config->fromNumber;
                        }
                        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/'
                            . rawurlencode($config->accountSid) . '/Messages.json';
                        $response = service('curlrequest')->post($endpoint, [
                            'headers' => [
                                'Authorization' => 'Basic ' . base64_encode($config->accountSid . ':' . $config->authToken),
                            ],
                            'form_params' => $form,
                            'timeout' => $config->timeout,
                            'http_errors' => false,
                        ]);
                        $payload = json_decode((string) $response->getBody(), true);
                        $httpCode = $response->getStatusCode();
                        if ($httpCode >= 200 && $httpCode < 300 && is_array($payload)
                            && ! empty($payload['sid'])) {
                            // The gateway accepted the message; final handset delivery is not guaranteed.
                            $outcome = 'sent';
                            $changes['provider_message_id'] = mb_substr((string) $payload['sid'], 0, 64);
                            $changes['sent_at'] = date('Y-m-d H:i:s');
                            $changes['last_error'] = null;
                        } else {
                            // Do not persist Twilio's response body: it may contain account details.
                            $changes['last_error'] = 'SMS gateway rejected the request (HTTP ' . $httpCode . ')';
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
                        $attempts = (int) $locked['attempts'] + ($outcome === 'skipped' ? 0 : 1);
                        $changes['attempts'] = $attempts;
                        $changes['status'] = $outcome === 'sent' ? 'sent' : ($outcome === 'skipped' ? 'skipped' : ($attempts >= 3 ? 'failed' : 'queued'));
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
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK('cafeteria.sms-dispatch')");
        }
        return $stats;
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
            (new self())->flush(5);
        } catch (Throwable $exception) {
            log_message('error', 'SMS dispatch unavailable: {class}', ['class' => get_class($exception)]);
        }
    }
}
