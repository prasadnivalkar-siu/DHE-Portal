<?php

namespace App\Database\Migrations;

use App\Services\VendorApiKeyService;
use CodeIgniter\Database\Migration;

final class CreateApiVendors extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'contact_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'allowed_categories' => ['type' => 'TEXT'],
            'rate_limit_per_minute' => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'api_key_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'api_key_prefix' => ['type' => 'VARCHAR', 'constraint' => 16],
            'key_generated_at' => ['type' => 'DATETIME'],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('vendor_code');
        $this->forge->addUniqueKey('api_key_hash');
        $this->forge->createTable('api_vendors', true);

        // Retain the configured integration key, but bind it to one fixed vendor.
        // Authentication never falls back to .env after this migration.
        $legacyKey = trim((string) env('dhe.apiKey', ''));
        if ($legacyKey !== '' && $this->db->table('api_vendors')->where('vendor_code', 'default-vendor')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('api_vendors')->insert([
                'vendor_code' => 'default-vendor',
                'name' => 'Existing API integration',
                'notes' => 'Migrated from the shared API key. This key now identifies default-vendor only.',
                'allowed_categories' => json_encode(array_keys(VendorApiKeyService::CATEGORIES)),
                'rate_limit_per_minute' => max(1, (int) env('dhe.apiRateLimitPerMinute', 60)),
                'api_key_hash' => hash('sha256', $legacyKey),
                'api_key_prefix' => substr($legacyKey, 0, 12),
                'key_generated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('api_vendors', true);
    }
}
