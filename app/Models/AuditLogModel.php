<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowCallbacks = false;
    public function update($id = null, $row = null): bool { throw new \DomainException('Audit logs are immutable.'); }
    public function delete($id = null, bool $purge = false) { throw new \DomainException('Audit logs are immutable.'); }
    protected $allowedFields = ['user_id', 'action', 'entity_type', 'entity_id', 'details_json', 'ip_address', 'user_agent', 'created_at'];
}
