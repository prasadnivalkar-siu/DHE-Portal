<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminAuditLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'actor_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'target_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'metadata' => ['type' => 'TEXT', 'null' => true],
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('actor_user_id');
        $this->forge->addKey('target_user_id');
        $this->forge->addKey('action');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('target_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admin_audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admin_audit_logs', true);
    }
}
