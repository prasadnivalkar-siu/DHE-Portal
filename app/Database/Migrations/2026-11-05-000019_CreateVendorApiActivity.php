<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateVendorApiActivity extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_id' => ['type' => 'INT', 'unsigned' => true],
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'category' => ['type' => 'VARCHAR', 'constraint' => 20],
            'action' => ['type' => 'VARCHAR', 'constraint' => 20],
            'method' => ['type' => 'VARCHAR', 'constraint' => 10],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true],
            'is_replay' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'duration_ms' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'response_bytes' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'requested_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('requested_at');
        $this->forge->addKey(['vendor_id', 'requested_at']);
        $this->forge->addKey(['category', 'requested_at']);
        $this->forge->createTable('vendor_api_activity');
    }

    public function down(): void
    {
        $this->forge->dropTable('vendor_api_activity', true);
    }
}
