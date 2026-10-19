<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\Database\BaseConnection;

class AuditLogService
{
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /** Store only changed non-sensitive fields, inside the caller's transaction. */
    public function recordChange(string $action, string $entity, int $id, array $before, array $after, ?int $userId = null): void
    {
        $old = self::scrub($before);
        $new = self::scrub($after);
        $diff = [];
        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $diff[$key] = ['before' => $old[$key] ?? null, 'after' => $value];
            }
        }
        if ($diff !== []) {
            $this->record($action, $entity, $id, ['changes' => $diff], $userId);
        }
    }

    /** @param array<string|int, mixed> $details */
    public static function scrub(array $details): array
    {
        foreach ($details as $key => $value) {
            if (preg_match('/password|passphrase|secret|token|credential|authorization|email|phone|address|otp|totp|recovery|api.?key|ip.?device/i', (string) $key)) {
                unset($details[$key]);
            } elseif (is_array($value)) {
                $details[$key] = self::scrub($value);
            }
        }
        return $details;
    }

    public function record(string $action, ?string $entityType = null, ?int $entityId = null, array $details = [], ?int $userId = null): void
    {
        $details = self::scrub($details);
        $isCli = is_cli();
        $request = $isCli ? null : service('request');
        $actor = $isCli ? null : session()->get('user');
        $userId ??= is_array($actor) ? (int) ($actor['id'] ?? 0) ?: null : null;
        $row = [
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType ? mb_substr($entityType, 0, 80) : null,
            'entity_id' => $entityId,
            'details_json' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => $isCli ? null : mb_substr((string) $request->getIPAddress(), 0, 45),
            'user_agent' => $isCli
                ? mb_substr('cli:' . (basename($_SERVER['argv'][1] ?? 'spark')), 0, 255)
                : mb_substr((string) $request->getUserAgent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db) {
            $this->db->table('audit_logs')->insert($row);
            return;
        }
        (new AuditLogModel())->insert($row);
    }
}
