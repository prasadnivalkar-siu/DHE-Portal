<?php

namespace App\Models;

use CodeIgniter\Model;

class PlacementModel extends Model
{
    protected $table = 'placements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'academic_year', 'university_aishe_code', 'university_name',
        'institution_aishe_code', 'institution_name', 'institution_type_name', 'program_name',
        'course_name', 'company_name', 'package_1_to_3_lakh', 'package_3_to_5_lakh',
        'package_5_to_10_lakh', 'package_10_to_15_lakh', 'package_above_15_lakh',
        'import_job_id', 'source_row_number',
        'publication_status', 'published_at',
    ];
}
