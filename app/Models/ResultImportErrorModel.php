<?php

namespace App\Models;

use CodeIgniter\Model;

class ResultImportErrorModel extends Model
{
    protected $table = 'result_import_errors';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'import_job_id', 'row_number', 'field_name', 'raw_value', 'error_message',
        'row_payload', 'created_at',
    ];
}

