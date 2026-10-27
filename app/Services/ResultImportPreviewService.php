<?php

namespace App\Services;

use App\Libraries\ResultSchema;
use App\Libraries\AdmissionWorkbookReader;
use App\Models\ResultImportJobModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

final class ResultImportPreviewService
{
    private const CHUNK_SIZE = 500;
    private const PREVIEW_SIZE = 20;

    private BaseConnection $db;
    private ResultImportJobModel $jobs;

    public function __construct()
    {
        $this->db = db_connect();
        $this->jobs = new ResultImportJobModel();
    }

    public function processQueued(int $jobId): void
    {
        if (function_exists('set_time_limit')) {
            @\set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            \ignore_user_abort(true);
        }
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            throw new RuntimeException('The queued preview job does not exist.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('result_import_jobs')
            ->where('id', $jobId)
            ->where('status', 'queued_preview')
            ->update(['status' => 'previewing', 'started_at' => $now, 'updated_at' => $now]);
        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('The preview job has already been claimed, cancelled, or completed.');
        }

        try {
            $this->db->table('result_import_errors')->where('import_job_id', $jobId)->delete();
            $path = $this->resolveStoredPath((string) $job['stored_filename']);
            $workbook = new AdmissionWorkbookReader($path);
            $sheetInfo = $workbook->worksheetInfo();
            $estimatedRows = max(0, (int) $sheetInfo['totalRows'] - 1);
            $this->jobs->update($jobId, ['total_rows' => $estimatedRows]);
            $stats = ['valid' => 0, 'warning' => 0, 'duplicate' => 0, 'rejected' => 0, 'scanned' => 0];
            $preview = [];
            $seenKeys = [];
            $headerResult = null;
            $chunk = [];

            foreach ($workbook->rows() as $rowNumber => $row) {
                if ($rowNumber === 1) {
                    $headerResult = ResultSchema::mapHeaders($row);
                    if ($headerResult['missing'] !== [] || $headerResult['duplicates'] !== []) {
                        $parts = [];
                        if ($headerResult['missing'] !== []) {
                            $parts[] = 'Missing headers: ' . implode(', ', $headerResult['missing']);
                        }
                        if ($headerResult['duplicates'] !== []) {
                            $parts[] = 'Duplicate headers: ' . implode(', ', $headerResult['duplicates']);
                        }
                        $message = implode('. ', $parts) . '.';
                        $this->db->table('result_import_errors')->insert([
                            'import_job_id' => $jobId,
                            'row_number' => 1,
                            'field_name' => 'Header row',
                            'error_message' => $message,
                            'row_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
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

                $this->validateChunk($jobId, $chunk, $headerResult['map'], $stats, $preview, $seenKeys);
                $chunk = [];
                $this->jobs->update($jobId, [
                    'valid_rows' => $stats['valid'],
                    'warning_rows' => $stats['warning'],
                    'duplicate_rows' => $stats['duplicate'],
                    'rejected_rows' => $stats['rejected'],
                ]);
            }
            if ($headerResult === null) {
                throw new RuntimeException('The workbook header row is missing.');
            }
            if ($chunk !== []) {
                $this->validateChunk($jobId, $chunk, $headerResult['map'], $stats, $preview, $seenKeys);
                $this->jobs->update($jobId, [
                    'valid_rows' => $stats['valid'],
                    'warning_rows' => $stats['warning'],
                    'duplicate_rows' => $stats['duplicate'],
                    'rejected_rows' => $stats['rejected'],
                ]);
            }

            $this->jobs->update($jobId, [
                'status' => 'preview_ready',
                'failure_stage' => null,
                'total_rows' => $stats['scanned'],
                'valid_rows' => $stats['valid'],
                'warning_rows' => $stats['warning'],
                'duplicate_rows' => $stats['duplicate'],
                'rejected_rows' => $stats['rejected'],
                'error_rows' => $stats['rejected'],
                'preview_rows' => json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'previewed_at' => date('Y-m-d H:i:s'),
                'completed_at' => null,
                'summary' => json_encode(['message' => 'Validation complete. Review the preview, then confirm the import.'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $exception) {
            $current = $this->jobs->find($jobId);
            if (($current['status'] ?? null) === 'previewing') {
                $this->jobs->update($jobId, [
                    'status' => 'failed',
                    'failure_stage' => 'preview',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
                ]);
            }
            throw $exception;
        }
    }

    private function validateChunk(int $jobId, array $rows, array $map, array &$stats, array &$preview, array &$seenKeys): void
    {
        $candidates = [];
        $ordered = [];
        foreach ($rows as $rowNumber => $row) {
            if (ResultSchema::isEmptyRow($row)) {
                continue;
            }
            $stats['scanned']++;
            $normalized = ResultSchema::normalizeRow($row, $map);
            if ($normalized['errors'] !== []) {
                $stats['rejected']++;
                $this->saveErrors($jobId, $rowNumber, $row, $normalized['errors']);
                $ordered[] = ['row' => $rowNumber, 'status' => 'rejected', 'data' => $normalized['data'], 'messages' => array_column($normalized['errors'], 'message')];
                continue;
            }

            $data = $normalized['data'];
            $key = ResultSchema::recordKey($data);
            if (isset($seenKeys[$key])) {
                $stats['duplicate']++;
                $message = 'Duplicates Excel row ' . $seenKeys[$key] . '.';
                $ordered[] = ['row' => $rowNumber, 'status' => 'duplicate', 'data' => $data, 'messages' => [$message]];
                continue;
            }
            $seenKeys[$key] = $rowNumber;
            $candidates[$key] = ['row' => $rowNumber, 'data' => $data, 'hash' => ResultSchema::rowHash($data)];
            $ordered[] = ['key' => $key];
        }

        $existingRows = $candidates === [] ? [] : $this->db->table('results')->select('record_key, row_hash')->whereIn('record_key', array_keys($candidates))->get()->getResultArray();
        $existing = array_column($existingRows, 'row_hash', 'record_key');
        foreach ($ordered as $item) {
            if (isset($item['status'])) {
                $this->appendPreview($preview, $item['row'], $item['status'], $item['data'], $item['messages']);
                continue;
            }
            $key = $item['key'];
            $candidate = $candidates[$key];
            if (! isset($existing[$key])) {
                $stats['valid']++;
                $this->appendPreview($preview, $candidate['row'], 'valid', $candidate['data'], ['Ready to insert.']);
            } elseif (hash_equals((string) $existing[$key], (string) $candidate['hash'])) {
                $stats['duplicate']++;
                $this->appendPreview($preview, $candidate['row'], 'duplicate', $candidate['data'], ['Already exists with identical values; it will be skipped.']);
            } else {
                $stats['warning']++;
                $this->appendPreview($preview, $candidate['row'], 'warning', $candidate['data'], ['Matches an existing record; changed values will update the draft record.']);
            }
        }
    }

    private function saveErrors(int $jobId, int $rowNumber, array $row, array $errors): void
    {
        $now = date('Y-m-d H:i:s');
        $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $records = [];
        foreach ($errors as $error) {
            $records[] = [
                'import_job_id' => $jobId,
                'row_number' => $rowNumber,
                'field_name' => $error['field'],
                'raw_value' => is_scalar($error['value']) ? (string) $error['value'] : null,
                'error_message' => $error['message'],
                'row_payload' => $payload,
                'created_at' => $now,
            ];
        }
        $this->db->table('result_import_errors')->insertBatch($records);
    }

    private function appendPreview(array &$preview, int $rowNumber, string $status, array $data, array $messages): void
    {
        if (count($preview) >= self::PREVIEW_SIZE) {
            return;
        }
        $preview[] = [
            'rowNumber' => $rowNumber,
            'status' => $status,
            'academicYear' => $data['SESSION'] ?? 'NA',
            'examYear' => $data['YEAR'] ?? 'NA',
            'season' => $data['SEASON'] ?? 'NA',
            'univCode' => $data['UNIV_AISHE_CODE'] ?? 'NA',
            'organizationCode' => $data['ORG_AISHE_CODE'] ?? 'NA',
            'organizationName' => $data['ORG_NAME'] ?? 'NA',
            'studentId' => $data['PRN'] !== 'NA' ? $data['PRN'] : ($data['REGN_NO'] !== 'NA' ? $data['REGN_NO'] : ($data['RROLL'] ?? 'NA')),
            'course' => trim((string) ($data['COURSE_CODE'] ?? 'NA') . ' · ' . (string) ($data['COURSE_NAME'] ?? 'NA')),
            'result' => $data['RESULT'] ?? 'NA',
            'messages' => $messages,
        ];
    }

    private function resolveStoredPath(string $storedFilename): string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($storedFilename, '\\/'));
        $path = realpath(WRITEPATH . $relative);
        $root = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'results');
        if ($path === false || $root === false || ! is_file($path)) {
            throw new RuntimeException('The queued workbook file is missing.');
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $inside = PHP_OS_FAMILY === 'Windows' ? str_starts_with(strtolower($path), strtolower($prefix)) : str_starts_with($path, $prefix);
        if (! $inside) {
            throw new RuntimeException('The queued workbook path is invalid.');
        }
        return $path;
    }
}



