<?php

namespace App\Models;

use CodeIgniter\Model;

class IntakeModel extends Model
{
    protected $table = 'intakes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'academic_year', 'university_aishe_code', 'university_name',
        'institution_aishe_code', 'institution_name', 'institution_type_name', 'program_code',
        'program_name', 'course_name', 'level', 'total_intake', 'designation', 'faculty_permanent',
        'faculty_temporary', 'faculty_chb', 'import_job_id', 'source_row_number',
        'publication_status', 'published_at',
    ];
}
