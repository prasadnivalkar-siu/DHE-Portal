<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminAuditLogModel extends Model
{
    protected $table = 'admin_audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['actor_user_id', 'target_user_id', 'action', 'metadata', 'request_ip', 'created_at'];
}
