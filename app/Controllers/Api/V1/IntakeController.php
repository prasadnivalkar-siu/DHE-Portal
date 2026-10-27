<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\IntakeSchema;
use App\Services\IntakeApiDeliveryTracker;
use App\Services\IntakeApiIdempotencyService;
use App\Services\IntakeApiRateLimiter;
use DateTimeImmutable;
use Throwable;

final class IntakeController extends BaseController
{
    private const DEFAULT_PAGE_SIZE = 1000;
    private const MAX_PAGE_SIZE = 1000;

    public function index()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->fail('The request body must contain a valid JSON object.', 400);
        }

        $academicYear = trim((string) ($payload['Academic_Year'] ?? ''));
        $universityCode = trim((string) ($payload['University_AISHE_Code'] ?? ''));
        $pageNo = filter_var($payload['PageNo'] ?? 1, FILTER_VALIDATE_INT);
        $pageSize = filter_var($payload['PageSize'] ?? self::DEFAULT_PAGE_SIZE, FILTER_VALIDATE_INT);
        $lastUpdateInput = trim((string) ($payload['lastUpdateDate'] ?? ''));
        $vendorCode = trim((string) ($payload['Vendor_Code'] ?? $this->request->getHeaderLine('X-Vendor-Code')));
        $vendorCode = $vendorCode === '' ? 'default-vendor' : $vendorCode;
        $idempotencyKey = trim((string) ($payload['Idempotency_Key'] ?? $this->request->getHeaderLine('Idempotency-Key')));
        $redeliveryRecordRaw = $payload['Redelivery_From_Record_ID'] ?? null;
        $redeliveryFromRecordId = ($redeliveryRecordRaw === null || $redeliveryRecordRaw === '') ? null : filter_var($redeliveryRecordRaw, FILTER_VALIDATE_INT);
        $redeliveryDateInput = trim((string) ($payload['Redelivery_From_Date'] ?? ''));
        $redeliveryFromDate = null;
        $validationErrors = [];

        if ($academicYear === '') {
            $validationErrors['Academic_Year'] = 'Academic_Year is required.';
        }
        if ($universityCode === '') {
            $validationErrors['University_AISHE_Code'] = 'University_AISHE_Code is required.';
        }
        if ($pageNo === false || $pageNo < 1) {
            $validationErrors['PageNo'] = 'PageNo must be a whole number greater than zero.';
        }
        if ($pageSize === false || $pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            $validationErrors['PageSize'] = 'PageSize must be between 1 and ' . self::MAX_PAGE_SIZE . '.';
        }
        if (strlen($vendorCode) > 100 || preg_match('/^[a-zA-Z0-9._-]+$/', $vendorCode) !== 1) {
            $validationErrors['Vendor_Code'] = 'Vendor_Code may contain letters, numbers, periods, underscores, and hyphens, up to 100 characters.';
        }
        if ($idempotencyKey !== '' && (strlen($idempotencyKey) > 100 || preg_match('/^[a-zA-Z0-9._:-]{8,100}$/', $idempotencyKey) !== 1)) {
            $validationErrors['Idempotency_Key'] = 'Idempotency_Key must be 8–100 characters using letters, numbers, periods, underscores, colons, or hyphens.';
        }
        if ($redeliveryFromRecordId === false || ($redeliveryFromRecordId !== null && $redeliveryFromRecordId < 1)) {
            $validationErrors['Redelivery_From_Record_ID'] = 'Redelivery_From_Record_ID must be a record ID greater than zero.';
        }

        $lastUpdateDate = null;
        if ($lastUpdateInput !== '') {
            try {
                $lastUpdateDate = $this->parseLastUpdateDate($lastUpdateInput);
            } catch (Throwable) {
                $validationErrors['lastUpdateDate'] = 'lastUpdateDate must use YYYY-MM-DD or a valid ISO 8601 date-time.';
            }
        }
        if ($redeliveryDateInput !== '') {
            try {
                $redeliveryFromDate = $this->parseLastUpdateDate($redeliveryDateInput);
            } catch (Throwable) {
                $validationErrors['Redelivery_From_Date'] = 'Redelivery_From_Date must use YYYY-MM-DD or a valid ISO 8601 date-time.';
            }
        }
        if ($redeliveryFromRecordId !== null && $redeliveryDateInput !== '') {
            $validationErrors['redelivery'] = 'Select either Redelivery_From_Record_ID or Redelivery_From_Date, not both.';
        }
        if (($redeliveryFromRecordId !== null || $redeliveryDateInput !== '') && $lastUpdateInput !== '') {
            $validationErrors['lastUpdateDate'] = 'lastUpdateDate cannot be combined with a redelivery starting point.';
        }

        if ($validationErrors !== []) {
            $this->writeFailureLog($vendorCode, $academicYear, $universityCode, $lastUpdateDate, (int) ($pageNo ?: 1), (int) ($pageSize ?: self::DEFAULT_PAGE_SIZE), 422);

            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'The API payload is invalid.',
                'errors' => $validationErrors,
            ]);
        }

        try {
            $rate = (new IntakeApiRateLimiter())->consume($vendorCode);
        } catch (Throwable $exception) {
            log_message('error', 'API rate-limit check failed: {message}', ['message' => $exception->getMessage()]);
            return $this->fail('The request could not be authorized for delivery. Please retry.', 503);
        }
        if (! $rate['allowed']) {
            $this->writeFailureLog($vendorCode, $academicYear, $universityCode, $lastUpdateDate, (int) $pageNo, (int) $pageSize, 429);
            return $this->response->setStatusCode(429)
                ->setHeader('Retry-After', (string) $rate['retryAfter'])
                ->setHeader('X-RateLimit-Limit', (string) $rate['limit'])
                ->setHeader('X-RateLimit-Remaining', '0')
                ->setJSON(['success' => false, 'message' => 'Rate limit exceeded. Retry after ' . $rate['retryAfter'] . ' seconds.']);
        }

        $idempotency = new IntakeApiIdempotencyService();
        $requestHash = $idempotency->requestHash($payload);

        $db = db_connect();
        $deliveryMode = ($redeliveryFromRecordId !== null || $redeliveryFromDate !== null) ? 'redelivery' : 'standard';
        $resumeLog = null;
        $effectivePageNo = (int) $pageNo;
        if ($deliveryMode === 'standard' && $lastUpdateDate !== null) {
            $resumeLog = $db->table('intake_api_logs')
                ->where('vendor_code', $vendorCode)
                ->where('academic_year', $academicYear)
                ->where('university_aishe_code', $universityCode)
                ->where('last_update_date', $lastUpdateDate)
                ->where('page_size', (int) $pageSize)
                ->where('response_code', 200)
                ->where('records_returned >', 0)
                ->where('response_last_record_id IS NOT NULL', null, false)
                ->where('response_last_updated_at IS NOT NULL', null, false)
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();

            // Incremental delivery is cursor-driven. PageNo is retained in the
            // payload for compatibility, while the effective sequence comes
            // from the last successful response for this vendor and date.
            $effectivePageNo = $resumeLog === null ? 1 : ((int) $resumeLog['page_no'] + 1);
        }

        $resumeRecordId = $resumeLog === null ? null : (int) $resumeLog['response_last_record_id'];
        $resumeUpdatedAt = $resumeLog['response_last_updated_at'] ?? null;
        $filters = static function ($builder) use ($academicYear, $universityCode, $lastUpdateDate, $resumeRecordId, $resumeUpdatedAt, $redeliveryFromRecordId, $redeliveryFromDate, $deliveryMode) {
            $builder->where('academic_year', $academicYear)
                ->where('university_aishe_code', $universityCode)
                ->where('publication_status', 'published');
            if ($deliveryMode === 'redelivery' && $redeliveryFromRecordId !== null) {
                $builder->where('id >=', $redeliveryFromRecordId);
            } elseif ($deliveryMode === 'redelivery' && $redeliveryFromDate !== null) {
                $builder->where('updated_at >=', $redeliveryFromDate);
            } elseif ($lastUpdateDate !== null) {
                $builder->where('updated_at >', $lastUpdateDate);
            }
            if ($deliveryMode === 'standard' && $resumeRecordId !== null && $resumeUpdatedAt !== null) {
                $builder->groupStart()
                    ->where('updated_at >', $resumeUpdatedAt)
                    ->orGroupStart()
                        ->where('updated_at', $resumeUpdatedAt)
                        ->where('id >', $resumeRecordId)
                    ->groupEnd()
                ->groupEnd();
            }

            return $builder;
        };

        $remainingBeforeCall = $filters($db->table('intakes'))->countAllResults();
        $total = $remainingBeforeCall;
        if ($deliveryMode === 'standard' && $resumeLog !== null) {
            $delivered = $db->table('intake_api_delivery_records r')
                ->select('COUNT(DISTINCT r.intake_id) AS delivered_count', false)
                ->join('intake_api_logs l', 'l.id = r.api_log_id')
                ->where('l.vendor_code', $vendorCode)
                ->where('l.academic_year', $academicYear)
                ->where('l.university_aishe_code', $universityCode)
                ->where('l.last_update_date', $lastUpdateDate)
                ->where('l.page_size', (int) $pageSize)
                ->where('l.response_code', 200)
                ->get()
                ->getRowArray();
            $total = max(
                (int) $resumeLog['total_matching_records'],
                (int) ($delivered['delivered_count'] ?? 0) + $remainingBeforeCall,
            );
        }

        $offset = ($deliveryMode === 'redelivery' || $lastUpdateDate === null) ? ($effectivePageNo - 1) * (int) $pageSize : 0;
        $rows = $filters($db->table('intakes'))
            ->orderBy('updated_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit((int) $pageSize, $offset)
            ->get()
            ->getResultArray();

        $data = array_map(static fn (array $row): array => IntakeSchema::toApi($row), $rows);
        $lastUpdatedDate = null;
        if ($rows !== []) {
            $lastUpdatedDate = max(array_column($rows, 'updated_at'));
        }
        $totalPages = $total === 0 ? 0 : (int) ceil($total / (int) $pageSize);
        if ($idempotencyKey !== '') {
            try {
                $replay = $idempotency->claim($vendorCode, $idempotencyKey, $requestHash);
                if ($replay !== null) {
                    return $this->response->setStatusCode($replay['code'])
                        ->setHeader('X-Idempotency-Replayed', 'true')
                        ->setHeader('X-RateLimit-Limit', (string) $rate['limit'])
                        ->setHeader('X-RateLimit-Remaining', (string) $rate['remaining'])
                        ->setJSON($replay['payload']);
                }
            } catch (\RuntimeException $exception) {
                return $this->fail($exception->getMessage(), 409);
            }
        }
        try {
            $tracking = (new IntakeApiDeliveryTracker())->track(
                $vendorCode,
                $academicYear,
                $universityCode,
                $lastUpdateDate,
                $effectivePageNo,
                (int) $pageSize,
                $total,
                $totalPages,
                $rows,
                $this->request->getIPAddress(),
                $deliveryMode,
                $redeliveryFromRecordId === false ? null : $redeliveryFromRecordId,
                $redeliveryFromDate,
                $idempotencyKey === '' ? null : $idempotencyKey,
            );
        } catch (Throwable $exception) {
            if ($idempotencyKey !== '') {
                $idempotency->release($vendorCode, $idempotencyKey, $requestHash);
            }
            log_message('error', 'Could not persist intake API delivery: {message}', ['message' => $exception->getMessage()]);

            return $this->fail('The delivery could not be tracked, so no records were released. Please retry.', 500);
        }
        $tracking['resumeApplied'] = $resumeLog !== null;
        $tracking['resumedAfterDeliveryId'] = $resumeLog === null ? null : (int) $resumeLog['id'];
        $tracking['resumedAfterRecordId'] = $resumeRecordId;
        $tracking['resumedAfterUpdatedDate'] = $resumeUpdatedAt;

        $responsePayload = [
            'count' => count($data),
            'data' => $data,
            'pagination' => [
                'currentPage' => $effectivePageNo,
                'pageSize' => (int) $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'remainingBeforeCall' => $remainingBeforeCall,
                'remainingAfterCall' => max(0, $remainingBeforeCall - count($rows)),
            ],
            'lastUpdatedDate' => $lastUpdatedDate,
            'deliveryTracking' => $tracking,
            'success' => true,
        ];
        if ($idempotencyKey !== '') {
            try {
                $idempotency->store($vendorCode, $idempotencyKey, $requestHash, 200, $responsePayload);
            } catch (Throwable $exception) {
                log_message('error', 'Could not persist API idempotency response: {message}', ['message' => $exception->getMessage()]);
                return $this->fail('The delivery was tracked but its idempotency record could not be saved. Do not retry without checking Delivery ID ' . $tracking['deliveryId'] . '.', 500);
            }
        }

        return $this->response
            ->setHeader('X-RateLimit-Limit', (string) $rate['limit'])
            ->setHeader('X-RateLimit-Remaining', (string) $rate['remaining'])
            ->setJSON($responsePayload);
    }

    public function acknowledge()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->fail('The request body must contain a valid JSON object.', 400);
        }
        $vendorCode = trim((string) ($payload['Vendor_Code'] ?? $this->request->getHeaderLine('X-Vendor-Code')));
        $deliveryId = filter_var($payload['Delivery_ID'] ?? null, FILTER_VALIDATE_INT);
        $key = trim((string) ($payload['Acknowledgement_Key'] ?? ''));
        $inputStatus = strtolower(trim((string) ($payload['Status'] ?? 'received successfully')));
        $status = in_array($inputStatus, ['received successfully', 'received_successfully', 'acknowledged'], true) ? 'acknowledged' : ($inputStatus === 'failed' ? 'failed' : null);
        if ($vendorCode === '' || $deliveryId === false || $deliveryId < 1 || $key === '' || $status === null) {
            return $this->fail('Vendor_Code, Delivery_ID, Acknowledgement_Key, and a valid Status are required.', 422);
        }
        $db = db_connect();
        $delivery = $db->table('intake_api_logs')->where('id', $deliveryId)->where('vendor_code', $vendorCode)->where('acknowledgement_key', $key)->get()->getRowArray();
        if ($delivery === null) {
            return $this->fail('The delivery acknowledgment could not be matched.', 404);
        }
        if ($delivery['ack_status'] !== $status) {
            $db->table('intake_api_logs')->where('id', $deliveryId)->update([
                'ack_status' => $status,
                'acknowledgement_message' => substr(trim((string) ($payload['Message'] ?? 'received successfully')), 0, 500),
                'acknowledged_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $this->response->setJSON([
            'success' => true,
            'deliveryId' => (int) $deliveryId,
            'status' => $status,
            'records' => (int) $delivery['records_returned'],
            'message' => $status === 'acknowledged' ? 'Delivery marked received successfully.' : 'Delivery marked failed by the vendor.',
        ]);
    }

    private function parseLastUpdateDate(string $input): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input);
            $issues = DateTimeImmutable::getLastErrors();
            if ($date === false || ($issues !== false && ($issues['warning_count'] > 0 || $issues['error_count'] > 0))) {
                throw new \InvalidArgumentException('Invalid date.');
            }

            return $date->format('Y-m-d 00:00:00');
        }

        return (new DateTimeImmutable($input))->format('Y-m-d H:i:s');
    }

    private function writeFailureLog(string $vendorCode, string $academicYear, string $universityCode, ?string $lastUpdateDate, int $pageNo, int $pageSize, int $code): void
    {
        try {
            db_connect()->table('intake_api_logs')->insert([
                'vendor_code' => substr($vendorCode ?: 'default-vendor', 0, 100),
                'academic_year' => $academicYear ?: null,
                'university_aishe_code' => $universityCode ?: null,
                'last_update_date' => $lastUpdateDate,
                'page_no' => max(1, $pageNo),
                'page_size' => max(1, $pageSize),
                'records_returned' => 0,
                'response_code' => $code,
                'ack_status' => 'failed',
                'request_ip' => $this->request->getIPAddress(),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Could not write intake API log: {message}', ['message' => $exception->getMessage()]);
        }
    }

    private function fail(string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setJSON(['success' => false, 'message' => $message]);
    }
}


