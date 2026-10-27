<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowNotApplicableAdmissionValues extends Migration
{
    private const OPTIONAL_FIELDS = [
        'univ_name', 'college_name', 'college_state', 'college_district', 'college_taluka',
        'college_city_village', 'college_pincode', 'admission_application_form_no',
        'admission_date', 'gender', 'religion', 'category', 'caste', 'admitted_category',
        'student_location_category', 'student_country', 'student_state', 'student_district',
        'student_taluka', 'student_city_village', 'student_location_pincode', 'prn',
        'student_id', 'receipt_id', 'receipt_no', 'receipt_amount', 'physical_disability',
        'marital_status', 'medium_of_appearance', 'medium_of_instructions', 'mode_of_learning',
        'program_code', 'program', 'specialization', 'program_type', 'program_part',
        'program_term', 'eligibility_status', 'eligibility_date', 'all_documents_submitted',
        'abc_id',
    ];

    public function up(): void
    {
        $this->forge->modifyColumn('admissions', [
            'admission_date' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'eligibility_date' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'receipt_amount' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'pending_documents_count' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NA'],
        ]);

        foreach (self::OPTIONAL_FIELDS as $field) {
            $this->db->table('admissions')->where($field, null)->update([$field => 'NA']);
        }
    }

    public function down(): void
    {
        foreach (['admission_date', 'eligibility_date', 'receipt_amount'] as $field) {
            $this->db->table('admissions')->where($field, 'NA')->update([$field => null]);
        }
        $this->db->table('admissions')->where('pending_documents_count', 'NA')->update(['pending_documents_count' => '0']);

        $this->forge->modifyColumn('admissions', [
            'admission_date' => ['type' => 'DATE', 'null' => true],
            'eligibility_date' => ['type' => 'DATE', 'null' => true],
            'receipt_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'pending_documents_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
    }
}
