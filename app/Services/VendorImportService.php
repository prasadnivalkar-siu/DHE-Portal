<?php

namespace App\Services;

use App\Libraries\VendorImportSchema;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

final class VendorImportService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function consumeRate(int $vendorId, string $category, int $limit): array
    {
        $window = date('Y-m-d H:i:00');
        $this->db->transBegin();
        try {
            $this->db->query('INSERT INTO vendor_api_import_rate_limits (vendor_id, category, window_started_at, request_count) VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE request_count = IF(window_started_at = VALUES(window_started_at), request_count + 1, 1), window_started_at = VALUES(window_started_at)', [$vendorId, $category, $window]);
            $row = $this->db->table('vendor_api_import_rate_limits')->where('vendor_id', $vendorId)->where('category', $category)->get()->getRowArray();
            $this->commit();
            return ['allowed' => (int) $row['request_count'] <= $limit, 'limit' => $limit,
                'remaining' => max(0, $limit - (int) $row['request_count']), 'retryAfter' => 60 - (int) date('s')];
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** Validate and stage a batch atomically; published data is not modified here. */
    public function submit(string $category, int $vendorId, string $vendorCode, string $key, array $records, string $ip): array
    {
        [$schema, $prefix, $table] = VendorImportSchema::definition($category);
        $hash = VendorImportSchema::requestHash($records);
        $this->db->transBegin();
        try {
            // Serializes same-vendor requests, including simultaneous retries of a key.
            $vendor = $this->db->query('SELECT * FROM api_vendors WHERE id = ? FOR UPDATE', [$vendorId])->getRowArray();
            if ($vendor === null || (int) $vendor['is_active'] !== 1 || ! in_array($category, json_decode($vendor['ingestion_categories'] ?? '[]', true) ?? [], true)) {
                throw new DomainException('This vendor is not authorized to submit this category.', 403);
            }
            $institutionScope = new VendorInstitutionScope($vendor);
            $previous = $this->db->table('vendor_api_imports')->where('vendor_id', $vendorId)->where('category', $category)->where('idempotency_key', $key)->get()->getRowArray();
            if ($previous !== null) {
                if (! hash_equals($previous['request_hash'], $hash)) {
                    throw new DomainException('This Idempotency-Key was already used with different records.', 409);
                }
                $this->assertBatchScope($previous, $institutionScope);
                $this->commit();
                return ['code' => (int) $previous['response_code'], 'payload' => json_decode($previous['response_payload'], true), 'replayed' => true];
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table($prefix . '_import_jobs')->insert([
                'source_type' => 'vendor_api', 'source_vendor_code' => $vendorCode,
                'original_filename' => 'API submission — ' . $vendorCode,
                'stored_filename' => '', 'file_hash' => $hash, 'status' => 'completed',
                'workflow_status' => 'draft', 'total_rows' => count($records),
                'created_at' => $now, 'updated_at' => $now, 'started_at' => $now, 'completed_at' => $now,
            ]);
            $jobId = (int) $this->db->insertID();
            $this->db->table('vendor_api_imports')->insert([
                'vendor_id' => $vendorId, 'vendor_code' => $vendorCode, 'category' => $category,
                'import_job_id' => $jobId, 'idempotency_key' => $key, 'request_hash' => $hash,
                'response_code' => 201, 'response_payload' => '{}', 'request_ip' => $ip, 'created_at' => $now,
            ]);
            $batchId = (int) $this->db->insertID();
            $counts = ['acceptedRows' => 0, 'duplicateRows' => 0, 'rejectedRows' => 0];
            $outcomes = [];
            $staged = [];
            $errorRows = [];
            $seen = [];
            $limits = [];
            foreach ($this->db->getFieldData($table) as $field) {
                if (in_array(strtolower($field->type), ['varchar', 'char'], true) && (int) $field->max_length > 0) {
                    $limits[$field->name] = (int) $field->max_length;
                }
            }
            foreach ($records as $index => $record) {
                $normalized = VendorImportSchema::normalize($category, $record);
                $data = $normalized['data'];
                $errors = $normalized['errors'];
                if ($errors === [] && ! $institutionScope->allows($category, $data)) {
                    $errors[] = ['field' => 'Institution access', 'message' => 'This vendor is not authorized to submit this university/institution.'];
                }
                foreach ($limits as $field => $maxLength) {
                    if (isset($data[$field]) && mb_strlen((string) $data[$field]) > $maxLength) {
                        $errors[] = ['field' => $field, 'message' => 'Value exceeds the maximum of ' . $maxLength . ' characters.'];
                    }
                }
                $baseHash = null;
                $outcome = 'accepted';
                if ($errors !== []) {
                    $outcome = 'rejected';
                } elseif (isset($seen[$data['record_key']])) {
                    // Conflicting duplicates in a batch must be corrected explicitly.
                    if ($seen[$data['record_key']] !== $data['row_hash']) {
                        $outcome = 'rejected';
                        $errors[] = ['field' => 'Record identity', 'message' => 'This batch contains different values for the same record identity.'];
                    } else {
                        $outcome = 'duplicate';
                    }
                } else {
                    $existing = $this->db->table($table)->where('record_key', $data['record_key'])->get()->getRowArray();
                    $baseHash = $existing['row_hash'] ?? null;
                    if ($existing !== null && ! $institutionScope->allows($category, $existing)) {
                        $outcome = 'rejected';
                        $errors[] = ['field' => 'Institution access', 'message' => 'The existing record is outside this vendor\'s institution access.'];
                    } elseif ($baseHash !== null && hash_equals($baseHash, $data['row_hash'])) {
                        $outcome = 'duplicate';
                    }
                }
                if ($outcome !== 'rejected') {
                    $seen[$data['record_key']] = $data['row_hash'];
                }
                $counts[match ($outcome) { 'accepted' => 'acceptedRows', 'duplicate' => 'duplicateRows', default => 'rejectedRows' }]++;
                $staged[] = [
                    'batch_id' => $batchId, 'row_number' => $index + 1, 'outcome' => $outcome,
                    'record_key' => $data['record_key'] ?? null, 'row_hash' => $data['row_hash'] ?? null,
                    'base_row_hash' => $baseHash, 'normalized_payload' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
                    'submitted_payload' => json_encode($record, JSON_THROW_ON_ERROR),
                    'errors' => $errors === [] ? null : json_encode($errors, JSON_THROW_ON_ERROR),
                ];
                $outcomes[] = ['rowNumber' => $index + 1, 'status' => $outcome, 'errors' => $errors];
                foreach ($errors as $error) {
                    $errorRows[] = [
                        'import_job_id' => $jobId, 'row_number' => $index + 1,
                        'field_name' => mb_substr($error['field'], 0, 150),
                        'error_message' => mb_substr($error['message'], 0, 500), 'created_at' => $now,
                    ];
                }
            }
            $this->db->table('vendor_api_import_rows')->insertBatch($staged, 50);
            if ($errorRows !== []) {
                $this->db->table($prefix . '_import_errors')->insertBatch($errorRows, 100);
            }
            $status = $counts['rejectedRows'] > 0 ? 'completed_with_errors' : 'completed';
            $this->db->table($prefix . '_import_jobs')->where('id', $jobId)->update([
                'status' => $status, 'valid_rows' => $counts['acceptedRows'],
                'duplicate_rows' => $counts['duplicateRows'], 'skipped_rows' => $counts['duplicateRows'],
                'rejected_rows' => $counts['rejectedRows'], 'error_rows' => $counts['rejectedRows'],
                'summary' => json_encode(['message' => 'Vendor API submission. Accepted records are staged as Draft and require administrator verification before publishing.', 'batchId' => $batchId]),
            ]);
            $code = $counts['acceptedRows'] + $counts['duplicateRows'] > 0 ? 201 : 422;
            $payload = [
                'success' => $code === 201, 'batchId' => $batchId, 'importJobId' => $jobId,
                'category' => $category, 'vendorCode' => $vendorCode, 'workflowStatus' => 'draft',
                'totalRows' => count($records), ...$counts,
                'message' => $counts['acceptedRows'] > 0
                    ? 'Accepted records are Draft. Publication requires administrator validation and approval.'
                    : ($counts['duplicateRows'] > 0 ? 'No new changes were accepted. Unchanged/duplicate rows do not require publication.' : 'No records were accepted. Correct the row errors and submit with a new idempotency key.'),
                'rows' => $outcomes,
            ];
            $this->db->table('vendor_api_imports')->where('id', $batchId)->update(['response_code' => $code, 'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
            $this->db->table('admin_audit_logs')->insert([
                'action' => 'vendor.import_submitted', 'metadata' => json_encode(['vendor_id' => $vendorId, 'vendor_code' => $vendorCode, 'category' => $category, 'batch_id' => $batchId, 'import_job_id' => $jobId, ...$counts]),
                'request_ip' => $ip, 'created_at' => $now,
            ]);
            $this->commit();
            return ['code' => $code, 'payload' => $payload, 'replayed' => false];
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function status(string $category, int $batchId, int $vendorId): ?array
    {
        [, $prefix] = VendorImportSchema::definition($category);
        $batch = $this->db->table('vendor_api_imports')->where('id', $batchId)->where('vendor_id', $vendorId)->where('category', $category)->get()->getRowArray();
        if ($batch === null) {
            return null;
        }
        $vendor = $this->db->table('api_vendors')->where('id', $vendorId)->get()->getRowArray();
        if ($vendor === null) {
            return null;
        }
        $this->assertBatchScope($batch, new VendorInstitutionScope($vendor));
        $job = $this->db->table($prefix . '_import_jobs')->where('id', $batch['import_job_id'])->get()->getRowArray();
        $result = json_decode($batch['response_payload'], true);
        $result['workflowStatus'] = $job['workflow_status'];
        $result['publishedAt'] = $job['published_at'];
        $result['insertedRows'] = (int) $job['inserted_rows'];
        $result['updatedRows'] = (int) $job['updated_rows'];
        return $result;
    }

    public function transition(string $category, int $jobId, string $action, int $actorId): string
    {
        [, $prefix, $table] = VendorImportSchema::definition($category);
        $rules = ['validate' => ['draft', 'validated', 'reviewed'], 'approve' => ['validated', 'approved', 'approved'], 'publish' => ['approved', 'published', 'published']];
        if (! isset($rules[$action])) {
            throw new RuntimeException('Unsupported workflow action.');
        }
        [$from, $to, $actorField] = $rules[$action];
        $this->db->transBegin();
        try {
            $job = $this->db->query('SELECT * FROM ' . $prefix . '_import_jobs WHERE id = ? FOR UPDATE', [$jobId])->getRowArray();
            if ($job === null || $job['source_type'] !== 'vendor_api' || $job['workflow_status'] !== $from) {
                throw new RuntimeException('The dataset must be ' . $from . ' before this action. Refresh and try again.');
            }
            $batch = $this->db->table('vendor_api_imports')->where('category', $category)->where('import_job_id', $jobId)->get()->getRowArray();
            $rows = $batch === null ? [] : $this->db->table('vendor_api_import_rows')->where('batch_id', $batch['id'])->where('outcome', 'accepted')->orderBy('record_key')->get()->getResultArray();
            if ($rows === []) {
                throw new RuntimeException('This submission contains no accepted changes to publish. Correct rejected rows or submit changed data with a new idempotency key.');
            }
            $vendor = $this->db->query('SELECT * FROM api_vendors WHERE id = ? FOR UPDATE', [$batch['vendor_id']])->getRowArray();
            if ($vendor === null || (int) $vendor['is_active'] !== 1 || ! in_array($category, json_decode($vendor['ingestion_categories'] ?? '[]', true) ?? [], true)) {
                throw new RuntimeException('The submitting vendor no longer has permission for this category.');
            }
            $institutionScope = new VendorInstitutionScope($vendor);
            foreach ($rows as $row) {
                if (! $institutionScope->allows($category, json_decode($row['normalized_payload'], true))) {
                    throw new RuntimeException('This draft contains an institution outside the submitting vendor\'s current access. Update the vendor scope or resubmit authorized data.');
                }
            }
            $now = date('Y-m-d H:i:s');
            $changes = ['workflow_status' => $to, $actorField . '_by' => $actorId, $actorField . '_at' => $now, 'updated_at' => $now];
            if ($action === 'publish') {
                $inserted = $updated = 0;
                foreach ($rows as $row) {
                    $current = $this->db->query('SELECT * FROM ' . $table . ' WHERE record_key = ? FOR UPDATE', [$row['record_key']])->getRowArray();
                    if ($current !== null && ! $institutionScope->allows($category, $current)) {
                        throw new RuntimeException('An existing record is outside the submitting vendor\'s current institution access. No changes were published.');
                    }
                    if ($current !== null && $current['publication_status'] === 'published' && hash_equals($current['row_hash'], $row['row_hash'])) {
                        continue;
                    }
                    if (($current['row_hash'] ?? null) !== $row['base_row_hash']) {
                        throw new RuntimeException('Record at submission row ' . $row['row_number'] . ' changed after this draft was received. Submit the latest data as a new batch; no changes from this batch were published.');
                    }
                    $data = json_decode($row['normalized_payload'], true, 512, JSON_THROW_ON_ERROR);
                    $data += ['import_job_id' => $jobId, 'source_row_number' => $row['row_number'], 'publication_status' => 'published', 'published_at' => $now, 'updated_at' => $now];
                    if ($current === null) {
                        $data['created_at'] = $now;
                        $this->db->table($table)->insert($data);
                        $inserted++;
                    } else {
                        $this->db->table($table)->where('id', $current['id'])->update($data);
                        $updated++;
                    }
                }
                $changes += ['inserted_rows' => $inserted, 'updated_rows' => $updated];
            }
            $this->db->table($prefix . '_import_jobs')->where('id', $jobId)->update($changes);
            $this->commit();
            return $to;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function commit(): void
    {
        if (! $this->db->transStatus() || ! $this->db->transCommit()) {
            throw new RuntimeException('The vendor import could not be saved. Retry with the same idempotency key.');
        }
    }

    private function assertBatchScope(array $batch, VendorInstitutionScope $scope): void
    {
        if ($scope->key() === '') {
            return;
        }
        $rows = $this->db->table('vendor_api_import_rows')->select('normalized_payload')->where('batch_id', $batch['id'])->whereIn('outcome', ['accepted', 'duplicate'])->get()->getResultArray();
        foreach ($rows as $row) {
            if (! $scope->allows($batch['category'], json_decode($row['normalized_payload'], true) ?? [])) {
                throw new DomainException('This submission is outside the vendor\'s current institution access.', 403);
            }
        }
    }
}
