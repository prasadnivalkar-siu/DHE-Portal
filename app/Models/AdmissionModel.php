<?php

namespace App\Models;

use CodeIgniter\Model;

class AdmissionModel extends Model
{
    protected $table = 'admissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'academic_year', 'univ_code', 'univ_name',
        'college_code', 'college_name', 'college_state', 'college_district',
        'college_taluka', 'college_city_village', 'college_pincode',
        'admission_application_form_no', 'admission_date', 'gender', 'religion',
        'category', 'caste', 'admitted_category', 'student_location_category',
        'student_country', 'student_state', 'student_district', 'student_taluka',
        'student_city_village', 'student_location_pincode', 'prn', 'student_id',
        'receipt_id', 'receipt_no', 'receipt_amount', 'physical_disability',
        'marital_status', 'medium_of_appearance', 'medium_of_instructions',
        'mode_of_learning', 'program_code', 'program', 'specialization',
        'program_type', 'program_part', 'program_term', 'eligibility_status',
        'eligibility_date', 'all_documents_submitted', 'pending_documents_count',
        'abc_id', 'import_job_id', 'source_row_number', 'publication_status', 'published_at',
    ];
}
