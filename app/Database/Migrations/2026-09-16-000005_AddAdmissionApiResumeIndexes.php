<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionApiResumeIndexes extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'CREATE INDEX idx_admissions_incremental_cursor'
            . ' ON admissions (academic_year, univ_code, updated_at, id)',
        );
        $this->db->query(
            'CREATE INDEX idx_api_logs_resume_lookup'
            . ' ON admission_api_logs'
            . ' (vendor_code, academic_year, university_aishe_code, last_update_date, page_size, response_code, id)',
        );
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_api_logs_resume_lookup ON admission_api_logs');
        $this->db->query('DROP INDEX idx_admissions_incremental_cursor ON admissions');
    }
}
