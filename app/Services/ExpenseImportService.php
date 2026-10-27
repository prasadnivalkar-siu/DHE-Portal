<?php

namespace App\Services;

use App\Libraries\ExpenseSchema;
use App\Libraries\AdmissionWorkbookReader;
use App\Models\ExpenseImportErrorModel;
use App\Models\ExpenseImportJobModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

final class ExpenseImportService
{
    private const CHUNK_SIZE = 500;

    private BaseConnection $db;
    private ExpenseImportJobModel $jobs;
    private ExpenseImportErrorModel $errors;

    public function __construct()
    {
        $this->db = db_connect();
        $this->jobs = new ExpenseImportJobModel();
        $this->errors = new ExpenseImportErrorModel();
    }

    public function import(string $path, string $originalFilename, string $storedFilename, ?string $progressToken = null): int
    {
        $jobId = $this->createQueuedJob($path, $originalFilename, $storedFilename, $progressToken);
        $this->processQueued($jobId);

        return $jobId;
    }

    public function createQueuedJob(string $path, string $originalFilename, string $storedFilename, ?string $progressToken = null, string $status = 'queued', ?int $importedBy = null): int
    {
        return (int) $this->jobs->insert([
            'progress_token' => $progressToken,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_hash' => hash_file('sha256', $path),
            'status' => $status,
            'imported_by' => $importedBy,
        ], true);
    }

    public function processQueued(int $jobId): void
    {
        $this->allowLongRunningImport();
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            throw new RuntimeException('The queued import job does not exist.');
        }

        $this->db->table('expense_import_jobs')
            ->where('id', $jobId)
            ->where('status', 'queued')
            ->update([
                'status' => 'processing',
                'started_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('The import job has already been claimed or completed.');
        }

        try {
            $this->db->table('expense_import_errors')->where('import_job_id', $jobId)->delete();
            $path = $this->resolveStoredPath((string) $job['stored_filename']);
            $this->processJob($jobId, $path);
        } catch (Throwable $exception) {
            $current = $this->jobs->find($jobId);
            if (($current['status'] ?? null) === 'processing') {
                $this->jobs->update($jobId, [
                    'status' => 'failed',
                    'failure_stage' => 'import',
                    'error_rows' => max(1, (int) ($current['error_rows'] ?? 0)),
                    'completed_at' => date('Y-m-d H:i:s'),
                    'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
                ]);
            }

            throw $exception;
        }
    }

    private function processJob(int $jobId, string $path): void
    {
        $completed = false;

        register_shutdown_function(function () use ($jobId, &$completed): void {
            if ($completed) {
                return;
            }

            $lastError = error_get_last();
            if ($lastError === null || ! in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            try {
                $job = (new ExpenseImportJobModel())->find($jobId);
                if (($job['status'] ?? null) === 'processing') {
                    (new ExpenseImportJobModel())->update($jobId, [
                        'status' => 'failed',
                        'failure_stage' => 'import',
                        'error_rows' => max(1, (int) ($job['error_rows'] ?? 0)),
                        'completed_at' => date('Y-m-d H:i:s'),
                        'summary' => json_encode([
                            'message' => 'Import stopped unexpectedly: ' . $lastError['message'],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            } catch (Throwable) {
                // A shutdown handler must never hide the original fatal error.
            }
        });

        try {
            $workbook = new AdmissionWorkbookReader($path);
            $sheetInfo = $workbook->worksheetInfo();
            $totalRows = max(0, (int) $sheetInfo['totalRows'] - 1);
            $this->jobs->update($jobId, ['total_rows' => $totalRows]);
            $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
            $seenKeys = [];
            $headerResult = null;
            $chunk = [];

            foreach ($workbook->rows() as $rowNumber => $row) {
                if ($rowNumber === 1) {
                    $headerResult = ExpenseSchema::mapHeaders($row);
                    if ($headerResult['missing'] !== [] || $headerResult['duplicates'] !== []) {
                        $messages = [];
                        if ($headerResult['missing'] !== []) {
                            $messages[] = 'Missing headers: ' . implode(', ', $headerResult['missing']);
                        }
                        if ($headerResult['duplicates'] !== []) {
                            $messages[] = 'Duplicate headers: ' . implode(', ', $headerResult['duplicates']);
                        }
                        $message = implode('. ', $messages) . '.';
                        $this->errors->insert([
                            'import_job_id' => $jobId,
                            'row_number' => 1,
                            'field_name' => 'Header row',
                            'error_message' => $message,
                            'row_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        throw new RuntimeException($message);
                    }
                    continue;
                }
                if ($headerResult === null) {
                    throw new RuntimeException('The workbook header row is missing.');
                }

                $chunk[$rowNumber] = $row;
                if (count($chunk) < self::CHUNK_SIZE) {
                    continue;
                }

                $this->processChunk($jobId, $chunk, $headerResult['map'], $stats, $seenKeys);
                $chunk = [];
                $this->jobs->update($jobId, [
                    'inserted_rows' => $stats['inserted'],
                    'updated_rows' => $stats['updated'],
                    'skipped_rows' => $stats['skipped'],
                    'error_rows' => $stats['errors'],
                ]);
            }
            if ($headerResult === null) {
                throw new RuntimeException('The workbook header row is missing.');
            }
            if ($chunk !== []) {
                $this->processChunk($jobId, $chunk, $headerResult['map'], $stats, $seenKeys);
                $this->jobs->update($jobId, [
                    'inserted_rows' => $stats['inserted'],
                    'updated_rows' => $stats['updated'],
                    'skipped_rows' => $stats['skipped'],
                    'error_rows' => $stats['errors'],
                ]);
            }

            $status = $stats['errors'] > 0 ? 'completed_with_errors' : 'completed';
            $this->jobs->update($jobId, [
                'status' => $status,
                'failure_stage' => null,
                'completed_at' => date('Y-m-d H:i:s'),
                'summary' => json_encode(['message' => 'Import completed.', 'worksheet' => $sheetInfo['worksheetName']], JSON_UNESCAPED_UNICODE),
            ]);
            $completed = true;
        } catch (Throwable $exception) {
            $job = $this->jobs->find($jobId);
            $this->jobs->update($jobId, [
                'status' => 'failed',
                'failure_stage' => 'import',
                'error_rows' => max(1, (int) ($job['error_rows'] ?? 0)),
                'completed_at' => date('Y-m-d H:i:s'),
                'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);
            $completed = true;

            throw $exception;
        }
    }

    private function resolveStoredPath(string $storedFilename): string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($storedFilename, '\\/'));
        $path = realpath(WRITEPATH . $relative);
        $uploadRoot = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'expenses');
        if ($path === false || $uploadRoot === false || ! is_file($path)) {
            throw new RuntimeException('The queued workbook file is missing.');
        }

        $rootPrefix = rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $isInside = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($path), strtolower($rootPrefix))
            : str_starts_with($path, $rootPrefix);
        if (! $isInside) {
            throw new RuntimeException('The queued workbook path is invalid.');
        }

        return $path;
    }

    private function allowLongRunningImport(): void
    {
        // PHP on Windows counts real elapsed time toward max_execution_time.
        // Imports should finish and update their audit log even if the browser
        // disconnects while a large workbook is being processed.
        if (function_exists('set_time_limit')) {
            @\set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            \ignore_user_abort(true);
        }
    }

    /** @param array{inserted: int, updated: int, skipped: int, errors: int} $stats */
    private function processChunk(int $jobId, array $rows, array $map, array &$stats, array &$seenKeys): void
    {
        $valid = [];
        $errorRows = [];
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $rowNumber => $row) {
            if (ExpenseSchema::isEmptyRow($row)) {
                $stats['skipped']++;
                continue;
            }

            $normalized = ExpenseSchema::normalizeRow($row, $map);
            if ($normalized['errors'] !== []) {
                $stats['errors']++;
                foreach ($normalized['errors'] as $error) {
                    $errorRows[] = [
                        'import_job_id' => $jobId,
                        'row_number' => $rowNumber,
                        'field_name' => $error['field'],
                        'raw_value' => $this->stringValue($error['value']),
                        'error_message' => $error['message'],
                        'row_payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                    ];
                }
                continue;
            }

            $data = $normalized['data'];
            $data['record_key'] = ExpenseSchema::recordKey($data);
            $data['row_hash'] = ExpenseSchema::rowHash($normalized['data']);
            $data['import_job_id'] = $jobId;
            $data['source_row_number'] = $rowNumber;
            $data['publication_status'] = 'draft';
            $data['published_at'] = null;
            if (isset($seenKeys[$data['record_key']])) {
                $stats['skipped']++;
                continue;
            }
            $seenKeys[$data['record_key']] = $rowNumber;
            $valid[$data['record_key']] = $data;
        }

        if ($errorRows !== []) {
            $this->db->table('expense_import_errors')->insertBatch($errorRows, 100);
        }
        if ($valid === []) {
            return;
        }

        $existingRows = $this->db->table('expenses')
            ->select('record_key, row_hash')
            ->whereIn('record_key', array_keys($valid))
            ->get()
            ->getResultArray();
        $existing = array_column($existingRows, 'row_hash', 'record_key');

        $inserts = [];
        $updates = [];
        foreach ($valid as $key => $data) {
            if (! isset($existing[$key])) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $inserts[] = $data;
                $stats['inserted']++;
                continue;
            }
            if (hash_equals((string) $existing[$key], $data['row_hash'])) {
                $stats['skipped']++;
                continue;
            }
            $data['updated_at'] = $now;
            $updates[] = $data;
            $stats['updated']++;
        }

        $this->db->transStart();
        if ($inserts !== []) {
            $this->db->table('expenses')->insertBatch($inserts, 100);
        }
        if ($updates !== []) {
            $this->db->table('expenses')->updateBatch($updates, 'record_key', 100);
        }
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('A database error occurred while saving an import chunk.');
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}



