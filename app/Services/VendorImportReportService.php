<?php

namespace App\Services;

use App\Libraries\VendorImportSchema;
use CodeIgniter\Exceptions\PageNotFoundException;

final class VendorImportReportService
{
    public function download(string $category, int $jobId, $response, bool $rejectedOnly = false)
    {
        [$schema] = VendorImportSchema::definition($category);
        $db = db_connect();
        $batch = $db->table('vendor_api_imports')->where('category', $category)->where('import_job_id', $jobId)->get()->getRowArray();
        if ($batch === null) {
            throw PageNotFoundException::forPageNotFound('Vendor submission not found.');
        }
        $query = $db->table('vendor_api_import_rows')->where('batch_id', $batch['id'])->orderBy('row_number');
        if ($rejectedOnly) {
            $query->where('outcome', 'rejected');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        $headers = $category === 'results' ? $schema::headers() : array_values($schema::API_FIELDS);
        fputcsv($stream, ['Row', 'Outcome', 'Error reasons', ...$headers, 'Original JSON']);
        $safe = static function (mixed $value): string {
            $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
            return preg_match('/^[=+@\-\t\r]/', $value) ? "'" . $value : $value;
        };
        foreach ($query->get()->getResultArray() as $row) {
            $errors = json_decode($row['errors'] ?? '[]', true) ?: [];
            $data = json_decode($row['normalized_payload'] ?? '[]', true) ?: [];
            $raw = json_decode($row['submitted_payload'], true);
            $api = $data !== [] ? $schema::toApi($data) : (is_array($raw) ? $raw : []);
            $values = array_map(static fn (string $header) => $safe($api[$header] ?? ''), $headers);
            fputcsv($stream, [$row['row_number'], $row['outcome'], $safe(implode(' | ', array_map(static fn (array $e): string => $e['field'] . ': ' . $e['message'], $errors))), ...$values, $safe($row['submitted_payload'])]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return $response->download($category . '-vendor-submission-' . $batch['id'] . ($rejectedOnly ? '-rejected' : '') . '.csv', $contents, true);
    }
}
