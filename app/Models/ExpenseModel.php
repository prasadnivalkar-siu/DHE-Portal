<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseModel extends Model
{
    protected $table = 'expenses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'record_key', 'row_hash', 'academic_year', 'university_aishe_code', 'university_name',
        'institution_aishe_code', 'institution_name', 'institution_type_name',
        'salary_expense', 'non_salary_expense',
        'import_job_id', 'source_row_number',
        'publication_status', 'published_at',
    ];
}

