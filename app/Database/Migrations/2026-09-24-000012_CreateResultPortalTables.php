<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateResultPortalTables extends Migration
{
    public function up(): void
    {
        $this->createImportJobs();
        $this->createResults();
        $this->createImportErrors();
        $this->createApiLogs();
        $this->createApiDeliveryRecords();
        $this->createApiSyncStates();
        $this->createApiIdempotency();
        $this->createApiRateLimits();
    }

    public function down(): void
    {
        $this->forge->dropTable('result_api_rate_limits', true);
        $this->forge->dropTable('result_api_idempotency', true);
        $this->forge->dropTable('result_api_sync_states', true);
        $this->forge->dropTable('result_api_delivery_records', true);
        $this->forge->dropTable('result_api_logs', true);
        $this->forge->dropTable('result_import_errors', true);
        $this->forge->dropTable('results', true);
        $this->forge->dropTable('result_import_jobs', true);
    }

    private function createImportJobs(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'progress_token' => ['type' => 'CHAR', 'constraint' => 32, 'null' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_filename' => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'queued_preview'],
            'failure_stage' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'workflow_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'imported_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'published_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'total_rows' => $this->counter(),
            'valid_rows' => $this->counter(),
            'warning_rows' => $this->counter(),
            'duplicate_rows' => $this->counter(),
            'rejected_rows' => $this->counter(),
            'inserted_rows' => $this->counter(),
            'updated_rows' => $this->counter(),
            'skipped_rows' => $this->counter(),
            'error_rows' => $this->counter(),
            'preview_rows' => ['type' => 'LONGTEXT', 'null' => true],
            'summary' => ['type' => 'TEXT', 'null' => true],
            'started_at' => $this->dateTime(),
            'previewed_at' => $this->dateTime(),
            'reviewed_at' => $this->dateTime(),
            'approved_at' => $this->dateTime(),
            'published_at' => $this->dateTime(),
            'cancelled_at' => $this->dateTime(),
            'completed_at' => $this->dateTime(),
            'created_at' => $this->dateTime(),
            'updated_at' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('progress_token', 'uq_result_import_progress');
        $this->forge->addKey('file_hash');
        $this->forge->addKey('status');
        $this->forge->addKey('workflow_status');
        $this->forge->addKey('imported_by');
        $this->forge->createTable('result_import_jobs', true);
    }

    private function createResults(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'record_key' => ['type' => 'CHAR', 'constraint' => 64],
            'row_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'academic_year' => $this->varchar(20, false),
            'exam_year' => $this->varchar(10, false),
            'season' => $this->varchar(50, false),
            'university_aishe_code' => $this->varchar(50, false),
            'university_name' => $this->varchar(255),
            'organization_aishe_code' => $this->varchar(50),
            'organization_code' => $this->varchar(100),
            'organization_name' => $this->varchar(500),
            'course_code' => $this->varchar(100),
            'course_name' => $this->varchar(500),
            'prn' => $this->varchar(100),
            'registration_no' => $this->varchar(100),
            'roll_no' => $this->varchar(100),
            'semester' => $this->varchar(20),
            'result_status' => $this->varchar(50),
            'result_payload' => ['type' => 'LONGTEXT'],
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'source_row_number' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'publication_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'published_at' => $this->dateTime(),
            'created_at' => $this->dateTime(),
            'updated_at' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('record_key', 'uq_results_record_key');
        $this->forge->addKey(['academic_year', 'university_aishe_code', 'exam_year', 'season'], false, false, 'idx_result_dataset');
        $this->forge->addKey('organization_aishe_code', false, false, 'idx_result_organization');
        $this->forge->addKey('import_job_id', false, false, 'idx_result_import_job');
        $this->forge->addKey(['publication_status', 'academic_year', 'university_aishe_code', 'exam_year', 'season', 'updated_at', 'id'], false, false, 'idx_result_published_cursor');
        $this->forge->addForeignKey('import_job_id', 'result_import_jobs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('results', true);
    }

    private function createImportErrors(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'row_number' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'field_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'raw_value' => ['type' => 'TEXT', 'null' => true],
            'error_message' => ['type' => 'VARCHAR', 'constraint' => 500],
            'row_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('import_job_id');
        $this->forge->addForeignKey('import_job_id', 'result_import_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('result_import_errors', true);
    }

    private function createApiLogs(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'delivery_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'standard'],
            'redelivery_from_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'redelivery_from_date' => $this->dateTime(),
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'cycle_key' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'academic_year' => $this->varchar(20),
            'university_aishe_code' => $this->varchar(50),
            'exam_year' => $this->varchar(10),
            'season' => $this->varchar(50),
            'last_update_date' => $this->dateTime(),
            'page_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'page_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 1000],
            'records_returned' => $this->counter(),
            'response_first_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'response_last_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'response_last_updated_at' => $this->dateTime(),
            'total_matching_records' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'total_pages' => $this->counter(),
            'is_final_page' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 200],
            'ack_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'acknowledgement_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'acknowledgement_message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'acknowledged_at' => $this->dateTime(),
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'requested_at' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('cycle_key');
        $this->forge->addKey('vendor_code');
        $this->forge->addKey(['vendor_code', 'idempotency_key'], false, false, 'idx_result_api_idempotency');
        $this->forge->addUniqueKey(['vendor_code', 'acknowledgement_key'], 'uq_result_api_ack_key');
        $this->forge->addKey('ack_status');
        $this->forge->addKey('requested_at');
        $this->forge->createTable('result_api_logs', true);
    }

    private function createApiDeliveryRecords(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'api_log_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'result_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'record_updated_at' => $this->dateTime(),
            'delivered_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['api_log_id', 'result_id'], 'uq_result_delivery_record');
        $this->forge->addKey('result_id');
        $this->forge->addKey('delivered_at');
        $this->forge->addForeignKey('api_log_id', 'result_api_logs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('result_api_delivery_records', true);
    }

    private function createApiSyncStates(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 20],
            'university_aishe_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'exam_year' => ['type' => 'VARCHAR', 'constraint' => 10],
            'season' => ['type' => 'VARCHAR', 'constraint' => 50],
            'current_cycle_key' => ['type' => 'CHAR', 'constraint' => 64],
            'request_last_update_date' => $this->dateTime(),
            'records_delivered_total' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'cycle_records_delivered' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'cycle_pages_delivered' => $this->counter(),
            'last_delivery_records' => $this->counter(),
            'last_page_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'last_page_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 1000],
            'last_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'last_record_updated_at' => $this->dateTime(),
            'completed_checkpoint_at' => $this->dateTime(),
            'sync_completed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'last_delivery_at' => $this->dateTime(),
            'created_at' => $this->dateTime(),
            'updated_at' => $this->dateTime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_code', 'academic_year', 'university_aishe_code', 'exam_year', 'season'], 'uq_result_api_sync_state');
        $this->forge->addKey('last_delivery_at');
        $this->forge->addForeignKey('last_delivery_id', 'result_api_logs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('result_api_sync_states', true);
    }

    private function createApiIdempotency(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'request_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'response_payload' => ['type' => 'MEDIUMBLOB', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'expires_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_code', 'idempotency_key'], 'uq_result_api_idem_key');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('result_api_idempotency', true);
    }

    private function createApiRateLimits(): void
    {
        $this->forge->addField([
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'window_started_at' => ['type' => 'DATETIME'],
            'request_count' => $this->counter(),
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('vendor_code', true);
        $this->forge->addKey('window_started_at');
        $this->forge->createTable('result_api_rate_limits', true);
    }

    /** @return array<string, mixed> */
    private function id(): array
    {
        return ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true];
    }

    /** @return array<string, mixed> */
    private function counter(): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'default' => 0];
    }

    /** @return array<string, mixed> */
    private function dateTime(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    /** @return array<string, mixed> */
    private function varchar(int $length, bool $nullable = true): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $length, 'null' => $nullable];
    }
}

