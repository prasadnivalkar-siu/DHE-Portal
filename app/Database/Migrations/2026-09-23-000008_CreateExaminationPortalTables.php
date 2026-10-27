<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateExaminationPortalTables extends Migration
{
    public function up(): void
    {
        $this->createImportJobs();
        $this->createExaminations();
        $this->createImportErrors();
        $this->createApiLogs();
        $this->createApiDeliveryRecords();
        $this->createApiSyncStates();
        $this->createApiIdempotency();
        $this->createApiRateLimits();
    }

    public function down(): void
    {
        $this->forge->dropTable('examination_api_rate_limits', true);
        $this->forge->dropTable('examination_api_idempotency', true);
        $this->forge->dropTable('examination_api_sync_states', true);
        $this->forge->dropTable('examination_api_delivery_records', true);
        $this->forge->dropTable('examination_api_logs', true);
        $this->forge->dropTable('examination_import_errors', true);
        $this->forge->dropTable('examinations', true);
        $this->forge->dropTable('examination_import_jobs', true);
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
        $this->forge->addUniqueKey('progress_token', 'uq_exam_import_progress');
        $this->forge->addKey('file_hash');
        $this->forge->addKey('status');
        $this->forge->addKey('workflow_status');
        $this->forge->addKey('imported_by');
        $this->forge->createTable('examination_import_jobs', true);
    }

    private function createExaminations(): void
    {
        $fields = [
            'id' => $this->id(),
            'record_key' => ['type' => 'CHAR', 'constraint' => 64],
            'row_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'univ_aishe_code' => $this->varchar(50, false),
            'univ_name' => $this->varchar(255),
            'academic_year' => $this->varchar(20, false),
            'exam_year' => $this->varchar(20),
            'exam_session' => $this->varchar(100),
            'course_abbr' => $this->varchar(100),
            'course_name' => $this->varchar(500),
            'course_code' => $this->varchar(100),
            'course_term' => $this->varchar(100),
            'course_pattern' => $this->varchar(150),
            'branch' => $this->varchar(255),
            'admission_form_no' => $this->varchar(100),
            'prn' => $this->varchar(100),
            'gender' => $this->varchar(50),
            'medium_of_appearance' => $this->varchar(100),
            'marital_status' => $this->varchar(50),
            'category' => $this->varchar(100),
            'student_state' => $this->varchar(150),
            'student_district' => $this->varchar(150),
            'taluka' => $this->varchar(150),
            'college_study_center_code' => $this->varchar(100),
            'college_study_center_name' => $this->varchar(500),
            'college_district' => $this->varchar(150),
            'college_taluka' => $this->varchar(150),
            'paper_code' => $this->varchar(100),
            'paper_name' => $this->varchar(500),
            'teaching_learning_method' => $this->varchar(150),
            'assessment_method' => $this->varchar(150),
            'assessment_type' => $this->varchar(150),
            'exam_form_number' => $this->varchar(100),
            'exam_appearance_type' => $this->varchar(100),
            'seat_number' => $this->varchar(100),
            'center_code' => $this->varchar(100),
            'center_name' => $this->varchar(500),
            'venue_code' => $this->varchar(100),
            'venue_name' => $this->varchar(500),
            'preferred_center_code' => $this->varchar(100),
            'preferred_center_name' => $this->varchar(500),
            'preferred_venue_code' => $this->varchar(100),
            'preferred_venue_name' => $this->varchar(500),
            'eligibility_status' => $this->varchar(100),
            'inward_date' => $this->varchar(10),
            'inward_time' => $this->varchar(8),
            'last_update_date' => $this->varchar(19),
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'source_row_number' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'publication_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'published_at' => $this->dateTime(),
            'created_at' => $this->dateTime(),
            'updated_at' => $this->dateTime(),
        ];
        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('record_key', 'uq_examinations_record_key');
        $this->forge->addKey(['academic_year', 'univ_aishe_code'], false, false, 'idx_exam_year_univ');
        $this->forge->addKey('college_study_center_code', false, false, 'idx_exam_college');
        $this->forge->addKey('import_job_id', false, false, 'idx_exam_import_job');
        $this->forge->addKey(['publication_status', 'academic_year', 'univ_aishe_code', 'updated_at', 'id'], false, false, 'idx_exam_published_cursor');
        $this->forge->addForeignKey('import_job_id', 'examination_import_jobs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('examinations', true);
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
        $this->forge->addForeignKey('import_job_id', 'examination_import_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('examination_import_errors', true);
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
        $this->forge->addKey(['vendor_code', 'idempotency_key'], false, false, 'idx_exam_api_idempotency');
        $this->forge->addUniqueKey(['vendor_code', 'acknowledgement_key'], 'uq_exam_api_ack_key');
        $this->forge->addKey('ack_status');
        $this->forge->addKey('requested_at');
        $this->forge->createTable('examination_api_logs', true);
    }

    private function createApiDeliveryRecords(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'api_log_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'examination_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'record_updated_at' => $this->dateTime(),
            'delivered_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['api_log_id', 'examination_id'], 'uq_exam_delivery_record');
        $this->forge->addKey('examination_id');
        $this->forge->addKey('delivered_at');
        $this->forge->addForeignKey('api_log_id', 'examination_api_logs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('examination_api_delivery_records', true);
    }

    private function createApiSyncStates(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 20],
            'university_aishe_code' => ['type' => 'VARCHAR', 'constraint' => 50],
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
        $this->forge->addUniqueKey(['vendor_code', 'academic_year', 'university_aishe_code'], 'uq_exam_api_sync_state');
        $this->forge->addKey('last_delivery_at');
        $this->forge->addForeignKey('last_delivery_id', 'examination_api_logs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('examination_api_sync_states', true);
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
        $this->forge->addUniqueKey(['vendor_code', 'idempotency_key'], 'uq_exam_api_idem_key');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('examination_api_idempotency', true);
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
        $this->forge->createTable('examination_api_rate_limits', true);
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
