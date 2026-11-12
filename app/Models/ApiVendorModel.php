<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiVendorModel extends Model
{
    protected $table = 'api_vendors';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'vendor_code', 'name', 'contact_email', 'notes', 'is_active', 'allowed_categories',
        'rate_limit_per_minute', 'api_key_hash', 'api_key_prefix', 'key_generated_at',
        'last_used_at', 'created_by', 'updated_by', 'ingestion_categories', 'api_key_encrypted',
        'institution_access_mode', 'institution_access_rules',
        'api_key_expires_at', 'current_key_last_used_at', 'previous_api_key_hash',
        'previous_api_key_prefix', 'previous_key_valid_until', 'previous_key_last_used_at',
    ];

    public function findByKeyHash(string $hash): ?array
    {
        return $this->groupStart()->where('api_key_hash', $hash)->orWhere('previous_api_key_hash', $hash)->groupEnd()->first();
    }

    public function recordUsage(int $id, ?string $keyHash = null): void
    {
        // Usage is separate from the administrator's last-modified timestamp.
        $now = date('Y-m-d H:i:s');
        if (! $this->db->query('UPDATE api_vendors SET last_used_at = ?, current_key_last_used_at = IF(api_key_hash = ?, ?, current_key_last_used_at), previous_key_last_used_at = IF(previous_api_key_hash = ?, ?, previous_key_last_used_at) WHERE id = ?', [$now, $keyHash, $now, $keyHash, $now, $id])) {
            throw new \RuntimeException('Could not record vendor API usage.');
        }
    }

    public function storeEncryptedKey(int $id, string $hash, string $encrypted): bool
    {
        return $this->builder()->where('id', $id)->where('api_key_hash', $hash)
            ->where('api_key_encrypted IS NULL', null, false)->update(['api_key_encrypted' => $encrypted])
            && $this->db->affectedRows() === 1;
    }
}
