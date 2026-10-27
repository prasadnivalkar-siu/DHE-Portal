<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdmissionPortalTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_filename' => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'processing'],
            'total_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'inserted_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'skipped_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'error_rows' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'summary' => ['type' => 'TEXT', 'null' => true],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('file_hash');
        $this->forge->addKey('status');
        $this->forge->createTable('admission_import_jobs', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'record_key' => ['type' => 'CHAR', 'constraint' => 64],
            'row_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 20],
            'univ_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'univ_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'college_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'college_name' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'college_state' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'college_district' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'college_taluka' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'college_city_village' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'college_pincode' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'admission_application_form_no' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'admission_date' => ['type' => 'DATE', 'null' => true],
            'gender' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'religion' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'caste' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'admitted_category' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'student_location_category' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'student_country' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'student_state' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'student_district' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'student_taluka' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'student_city_village' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'student_location_pincode' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'prn' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'student_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'receipt_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'receipt_no' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'receipt_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'physical_disability' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'marital_status' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'medium_of_appearance' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'medium_of_instructions' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'mode_of_learning' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'program_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'program' => ['type' => 'VARCHAR', 'constraint' => 750, 'null' => true],
            'specialization' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'program_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'program_part' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'program_term' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'eligibility_status' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'eligibility_date' => ['type' => 'DATE', 'null' => true],
            'all_documents_submitted' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'pending_documents_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'abc_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'source_row_number' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('record_key', 'uq_admissions_record_key');
        $this->forge->addKey(['academic_year', 'univ_code'], false, false, 'idx_admissions_year_university');
        $this->forge->addKey('college_code');
        $this->forge->addKey('updated_at');
        $this->forge->addKey('import_job_id');
        $this->forge->addForeignKey('import_job_id', 'admission_import_jobs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('admissions', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'row_number' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'field_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'raw_value' => ['type' => 'TEXT', 'null' => true],
            'error_message' => ['type' => 'VARCHAR', 'constraint' => 500],
            'row_payload' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('import_job_id');
        $this->forge->addForeignKey('import_job_id', 'admission_import_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admission_import_errors', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'academic_year' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'university_aishe_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'last_update_date' => ['type' => 'DATETIME', 'null' => true],
            'page_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'page_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 1000],
            'records_returned' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 200],
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'requested_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('requested_at');
        $this->forge->createTable('admission_api_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admission_api_logs', true);
        $this->forge->dropTable('admission_import_errors', true);
        $this->forge->dropTable('admissions', true);
        $this->forge->dropTable('admission_import_jobs', true);
    }
}
