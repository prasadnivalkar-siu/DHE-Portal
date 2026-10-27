<?php

namespace App\Models;

use CodeIgniter\Model;

class ExaminationImportJobModel extends Model
{
    protected $table = 'examination_import_jobs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'progress_token', 'original_filename', 'stored_filename', 'file_hash', 'status', 'failure_stage',
        'workflow_status', 'imported_by', 'reviewed_by', 'approved_by', 'published_by', 'total_rows',
        'valid_rows', 'warning_rows', 'duplicate_rows', 'rejected_rows', 'preview_rows',
        'inserted_rows', 'updated_rows', 'skipped_rows', 'error_rows', 'summary',
        'started_at', 'previewed_at', 'reviewed_at', 'approved_at', 'published_at',
        'cancelled_at', 'completed_at',
    ];
}
