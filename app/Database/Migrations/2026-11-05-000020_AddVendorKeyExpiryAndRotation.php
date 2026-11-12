<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddVendorKeyExpiryAndRotation extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('api_vendors', [
            'api_key_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'current_key_last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'previous_api_key_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'previous_api_key_prefix' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'previous_key_valid_until' => ['type' => 'DATETIME', 'null' => true],
            'previous_key_last_used_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->db->query('CREATE UNIQUE INDEX uq_vendor_previous_key_hash ON api_vendors (previous_api_key_hash)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX uq_vendor_previous_key_hash ON api_vendors');
        $this->forge->dropColumn('api_vendors', ['api_key_expires_at', 'current_key_last_used_at', 'previous_api_key_hash', 'previous_api_key_prefix', 'previous_key_valid_until', 'previous_key_last_used_at']);
    }
}
