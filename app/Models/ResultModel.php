<?php

namespace App\Models;

use CodeIgniter\Model;

class ResultModel extends Model
{
    protected $table = 'results';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'academic_year', 'exam_year', 'season',
        'university_aishe_code', 'university_name', 'organization_aishe_code',
        'organization_code', 'organization_name', 'course_code', 'course_name',
        'prn', 'registration_no', 'roll_no', 'semester', 'result_status', 'result_payload',
        'import_job_id', 'source_row_number',
        'publication_status', 'published_at',
    ];
}

