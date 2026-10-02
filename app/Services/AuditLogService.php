<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\Database\BaseConnection;

class AuditLogService
{
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    public function record(string $action, ?string $entityType = null, ?int $entityId = null, array $details = [], ?int $userId = null): void
    {
        $request = service('request');
        $sensitiveKeys = ['password', 'password_hash', 'token', 'email', 'phone', 'address'];
        foreach ($sensitiveKeys as $key) {
            unset($details[$key]);
        }
        $actor = session()->get('user');
        $userId ??= is_array($actor) ? (int) ($actor['id'] ?? 0) ?: null : null;
        $row = [
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType ? mb_substr($entityType, 0, 80) : null,
            'entity_id' => $entityId,
            'details_json' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => mb_substr((string) $request->getIPAddress(), 0, 45),
            'user_agent' => mb_substr((string) $request->getUserAgent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db) {
            $this->db->table('audit_logs')->insert($row);
            return;
        }
        (new AuditLogModel())->insert($row);
    }
}
