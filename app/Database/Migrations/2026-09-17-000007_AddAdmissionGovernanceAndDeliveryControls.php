<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionGovernanceAndDeliveryControls extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('admission_import_jobs', [
            'valid_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'total_rows'],
            'warning_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'valid_rows'],
            'duplicate_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'warning_rows'],
            'rejected_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'duplicate_rows'],
            'preview_rows' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'summary'],
            'failure_stage' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'status'],
            'workflow_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft', 'after' => 'failure_stage'],
            'imported_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'workflow_status'],
            'reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'imported_by'],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'reviewed_by'],
            'published_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'approved_by'],
            'previewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'started_at'],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'previewed_at'],
            'approved_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'reviewed_at'],
            'published_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'approved_at'],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'published_at'],
        ]);
        $this->db->query('CREATE INDEX idx_import_jobs_workflow ON admission_import_jobs (workflow_status)');
        $this->db->query('CREATE INDEX idx_import_jobs_imported_by ON admission_import_jobs (imported_by)');

        $this->forge->addColumn('admissions', [
            'publication_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft', 'after' => 'source_row_number'],
            'published_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'publication_status'],
        ]);
        $this->db->query('UPDATE admissions SET publication_status = \'published\', published_at = COALESCE(updated_at, created_at, NOW())');
        $this->db->query('UPDATE admission_import_jobs SET workflow_status = \'published\', published_at = COALESCE(completed_at, updated_at, created_at, NOW()) WHERE status IN (\'completed\', \'completed_with_errors\')');
        $this->db->query('UPDATE admission_import_jobs SET valid_rows = inserted_rows + updated_rows, duplicate_rows = skipped_rows, rejected_rows = error_rows WHERE status IN (\'completed\', \'completed_with_errors\')');
        $this->db->query('CREATE INDEX idx_admissions_published_cursor ON admissions (publication_status, academic_year, univ_code, updated_at, id)');

        $this->forge->addColumn('admission_api_logs', [
            'delivery_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'standard', 'after' => 'vendor_code'],
            'redelivery_from_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'delivery_mode'],
            'redelivery_from_date' => ['type' => 'DATETIME', 'null' => true, 'after' => 'redelivery_from_record_id'],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'redelivery_from_date'],
            'ack_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'response_code'],
            'acknowledgement_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'ack_status'],
            'acknowledgement_message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'acknowledgement_key'],
            'acknowledged_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'acknowledgement_message'],
        ]);
        $this->db->query('CREATE INDEX idx_api_log_idempotency ON admission_api_logs (vendor_code, idempotency_key)');
        $this->db->query('CREATE UNIQUE INDEX uq_api_log_ack_key ON admission_api_logs (vendor_code, acknowledgement_key)');
        $this->db->query('CREATE INDEX idx_api_log_ack_status ON admission_api_logs (ack_status)');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'request_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'response_payload' => ['type' => 'MEDIUMBLOB', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_code', 'idempotency_key'], 'uq_api_idempotency_key');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('admission_api_idempotency', true);

        $this->forge->addField([
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'window_started_at' => ['type' => 'DATETIME'],
            'request_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('vendor_code', true);
        $this->forge->addKey('window_started_at');
        $this->forge->createTable('admission_api_rate_limits', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admission_api_rate_limits', true);
        $this->forge->dropTable('admission_api_idempotency', true);
        $this->db->query('DROP INDEX idx_api_log_idempotency ON admission_api_logs');
        $this->db->query('DROP INDEX uq_api_log_ack_key ON admission_api_logs');
        $this->db->query('DROP INDEX idx_api_log_ack_status ON admission_api_logs');
        $this->forge->dropColumn('admission_api_logs', [
            'delivery_mode', 'redelivery_from_record_id', 'redelivery_from_date', 'idempotency_key',
            'ack_status', 'acknowledgement_key', 'acknowledgement_message', 'acknowledged_at',
        ]);
        $this->db->query('DROP INDEX idx_admissions_published_cursor ON admissions');
        $this->forge->dropColumn('admissions', ['publication_status', 'published_at']);
        $this->db->query('DROP INDEX idx_import_jobs_workflow ON admission_import_jobs');
        $this->db->query('DROP INDEX idx_import_jobs_imported_by ON admission_import_jobs');
        $this->forge->dropColumn('admission_import_jobs', [
            'valid_rows', 'warning_rows', 'duplicate_rows', 'rejected_rows', 'preview_rows',
            'failure_stage', 'workflow_status', 'imported_by', 'reviewed_by', 'approved_by',
            'published_by', 'previewed_at', 'reviewed_at', 'approved_at', 'published_at', 'cancelled_at',
        ]);
    }
}
