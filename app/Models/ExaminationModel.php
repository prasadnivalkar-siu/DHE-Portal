<?php

namespace App\Models;

use CodeIgniter\Model;

class ExaminationModel extends Model
{
    protected $table = 'examinations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'univ_aishe_code', 'univ_name', 'academic_year',
        'exam_year', 'exam_session', 'course_abbr', 'course_name', 'course_code',
        'course_term', 'course_pattern', 'branch', 'admission_form_no', 'prn',
        'gender', 'medium_of_appearance', 'marital_status', 'category', 'student_state',
        'student_district', 'taluka', 'college_study_center_code', 'college_study_center_name',
        'college_district', 'college_taluka', 'paper_code', 'paper_name',
        'teaching_learning_method', 'assessment_method', 'assessment_type', 'exam_form_number',
        'exam_appearance_type', 'seat_number', 'center_code', 'center_name', 'venue_code',
        'venue_name', 'preferred_center_code', 'preferred_center_name', 'preferred_venue_code',
        'preferred_venue_name', 'eligibility_status', 'inward_date', 'inward_time',
        'last_update_date', 'import_job_id', 'source_row_number', 'publication_status', 'published_at',
    ];
}
