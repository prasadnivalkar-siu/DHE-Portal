<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdmissionApiDeliveryTracking extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('admission_api_logs', [
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'legacy'],
            'cycle_key' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'response_first_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'response_last_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'response_last_updated_at' => ['type' => 'DATETIME', 'null' => true],
            'total_matching_records' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'total_pages' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_final_page' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->db->query('CREATE INDEX idx_admission_api_logs_cycle ON admission_api_logs (cycle_key)');
        $this->db->query('CREATE INDEX idx_admission_api_logs_vendor ON admission_api_logs (vendor_code)');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'api_log_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'admission_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'record_updated_at' => ['type' => 'DATETIME', 'null' => true],
            'delivered_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['api_log_id', 'admission_id'], 'uq_api_delivery_record');
        $this->forge->addKey('admission_id');
        $this->forge->addKey('delivered_at');
        $this->forge->addForeignKey('api_log_id', 'admission_api_logs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admission_api_delivery_records', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 20],
            'university_aishe_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'current_cycle_key' => ['type' => 'CHAR', 'constraint' => 64],
            'request_last_update_date' => ['type' => 'DATETIME', 'null' => true],
            'records_delivered_total' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'cycle_records_delivered' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'cycle_pages_delivered' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'last_delivery_records' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'last_page_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'last_page_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 1000],
            'last_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'last_record_updated_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_checkpoint_at' => ['type' => 'DATETIME', 'null' => true],
            'sync_completed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'last_delivery_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_code', 'academic_year', 'university_aishe_code'], 'uq_admission_api_sync_state');
        $this->forge->addKey('last_delivery_at');
        $this->forge->addForeignKey('last_delivery_id', 'admission_api_logs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admission_api_sync_states', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admission_api_sync_states', true);
        $this->forge->dropTable('admission_api_delivery_records', true);
        $this->forge->dropColumn('admission_api_logs', [
            'vendor_code',
            'cycle_key',
            'response_first_record_id',
            'response_last_record_id',
            'response_last_updated_at',
            'total_matching_records',
            'total_pages',
            'is_final_page',
        ]);
    }
}
