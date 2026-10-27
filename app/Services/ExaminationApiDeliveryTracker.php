<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

final class ExaminationApiDeliveryTracker
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int|string|bool|null>
     */
    public function track(
        string $vendorCode,
        string $academicYear,
        string $universityCode,
        ?string $requestLastUpdateDate,
        int $pageNo,
        int $pageSize,
        int $total,
        int $totalPages,
        array $rows,
        string $requestIp,
        string $deliveryMode = 'standard',
        ?int $redeliveryFromRecordId = null,
        ?string $redeliveryFromDate = null,
        ?string $idempotencyKey = null,
    ): array {
        $deliveredAt = date('Y-m-d H:i:s');
        $returned = count($rows);
        $firstRecordId = $rows === [] ? null : (int) $rows[0]['id'];
        $lastRecordId = $rows === [] ? null : (int) $rows[array_key_last($rows)]['id'];
        $responseLastUpdatedAt = $rows === [] ? null : max(array_column($rows, 'updated_at'));
        $acknowledgementKey = bin2hex(random_bytes(24));
        $cycleKey = hash('sha256', implode('|', [
            strtolower($vendorCode),
            strtolower($academicYear),
            strtolower($universityCode),
            $requestLastUpdateDate ?? 'full',
            (string) $pageSize,
            $deliveryMode,
            (string) ($redeliveryFromRecordId ?? ''),
            (string) ($redeliveryFromDate ?? ''),
        ]));

        $this->db->transBegin();
        try {
            $this->db->table('examination_api_logs')->insert([
                'vendor_code' => $vendorCode,
                'delivery_mode' => $deliveryMode,
                'redelivery_from_record_id' => $redeliveryFromRecordId,
                'redelivery_from_date' => $redeliveryFromDate,
                'idempotency_key' => $idempotencyKey,
                'cycle_key' => $cycleKey,
                'academic_year' => $academicYear,
                'university_aishe_code' => $universityCode,
                'last_update_date' => $requestLastUpdateDate,
                'page_no' => $pageNo,
                'page_size' => $pageSize,
                'records_returned' => $returned,
                'response_code' => 200,
                'ack_status' => $returned > 0 ? 'pending' : 'acknowledged',
                'acknowledgement_key' => $acknowledgementKey,
                'acknowledged_at' => $returned > 0 ? null : $deliveredAt,
                'request_ip' => $requestIp,
                'requested_at' => $deliveredAt,
                'response_first_record_id' => $firstRecordId,
                'response_last_record_id' => $lastRecordId,
                'response_last_updated_at' => $responseLastUpdatedAt,
                'total_matching_records' => $total,
                'total_pages' => $totalPages,
                'is_final_page' => $totalPages === 0 || $pageNo === $totalPages ? 1 : 0,
            ]);
            $deliveryId = (int) $this->db->insertID();

            if ($rows !== []) {
                $deliveryRows = array_map(static fn (array $row): array => [
                    'api_log_id' => $deliveryId,
                    'examination_id' => (int) $row['id'],
                    'record_updated_at' => $row['updated_at'] ?? null,
                    'delivered_at' => $deliveredAt,
                ], $rows);
                $this->db->table('examination_api_delivery_records')->insertBatch($deliveryRows, 250);
            }

            if ($deliveryMode === 'redelivery') {
                if ($this->db->transStatus() === false) {
                    throw new RuntimeException('The API redelivery transaction failed.');
                }
                $this->db->transCommit();
                return [
                    'deliveryId' => $deliveryId,
                    'vendorCode' => $vendorCode,
                    'deliveryMode' => $deliveryMode,
                    'acknowledgementKey' => $acknowledgementKey,
                    'acknowledgementStatus' => $returned > 0 ? 'pending' : 'acknowledged',
                    'recordsSentThisCall' => $returned,
                    'recordsDeliveredInCycle' => $returned,
                    'pagesDeliveredInCycle' => 1,
                    'totalRecordsDelivered' => $returned,
                    'firstRecordId' => $firstRecordId,
                    'lastRecordId' => $lastRecordId,
                    'responseLastUpdatedDate' => $responseLastUpdatedAt,
                    'syncCompleted' => $totalPages === 0 || $pageNo >= $totalPages,
                    'nextPageNo' => ($totalPages === 0 || $pageNo >= $totalPages) ? null : $pageNo + 1,
                    'nextIncrementalDate' => null,
                ];
            }

            $progress = $this->db->table('examination_api_delivery_records r')
                ->select('COUNT(DISTINCT r.examination_id) AS record_count', false)
                ->select('COUNT(DISTINCT l.page_no) AS page_count', false)
                ->selectMax('r.record_updated_at', 'max_updated_at')
                ->join('examination_api_logs l', 'l.id = r.api_log_id')
                ->where('l.cycle_key', $cycleKey)
                ->where('l.response_code', 200)
                ->get()
                ->getRowArray() ?? [];
            $cycleRecordCount = (int) ($progress['record_count'] ?? 0);
            $cyclePageCount = (int) ($progress['page_count'] ?? 0);
            $state = $this->db->table('examination_api_sync_states')
                ->where('vendor_code', $vendorCode)
                ->where('academic_year', $academicYear)
                ->where('university_aishe_code', $universityCode)
                ->get()
                ->getRowArray();
            $alreadyCompletedCycle = $state !== null
                && $state['current_cycle_key'] === $cycleKey
                && (int) $state['sync_completed'] === 1
                && (int) $state['cycle_records_delivered'] >= $total;
            if ($alreadyCompletedCycle) {
                $cycleRecordCount = max($cycleRecordCount, (int) $state['cycle_records_delivered']);
                $cyclePageCount = max($cyclePageCount, (int) $state['cycle_pages_delivered']);
            }
            $syncCompleted = $alreadyCompletedCycle
                || $total === 0
                || $cycleRecordCount >= $total;
            $cycleCheckpoint = $alreadyCompletedCycle
                ? ($state['completed_checkpoint_at'] ?? $progress['max_updated_at'] ?? null)
                : ($progress['max_updated_at'] ?? null);

            $recordsDeliveredTotal = (int) ($state['records_delivered_total'] ?? 0) + $returned;
            $completedCheckpoint = $syncCompleted
                ? ($cycleCheckpoint ?? $requestLastUpdateDate ?? ($state['completed_checkpoint_at'] ?? null))
                : ($state['completed_checkpoint_at'] ?? null);

            $this->db->table('examination_api_logs')
                ->where('id', $deliveryId)
                ->update(['is_final_page' => $syncCompleted ? 1 : 0]);

            $stateData = [
                'vendor_code' => $vendorCode,
                'academic_year' => $academicYear,
                'university_aishe_code' => $universityCode,
                'current_cycle_key' => $cycleKey,
                'request_last_update_date' => $requestLastUpdateDate,
                'records_delivered_total' => $recordsDeliveredTotal,
                'cycle_records_delivered' => $cycleRecordCount,
                'cycle_pages_delivered' => $cyclePageCount,
                'last_delivery_records' => $returned,
                'last_page_no' => $pageNo,
                'last_page_size' => $pageSize,
                'last_record_id' => $lastRecordId ?? ($state['last_record_id'] ?? null),
                'last_record_updated_at' => $responseLastUpdatedAt ?? ($state['last_record_updated_at'] ?? null),
                'completed_checkpoint_at' => $completedCheckpoint,
                'sync_completed' => $syncCompleted ? 1 : 0,
                'last_delivery_id' => $deliveryId,
                'last_delivery_at' => $deliveredAt,
                'updated_at' => $deliveredAt,
            ];
            if ($state === null) {
                $stateData['created_at'] = $deliveredAt;
                $this->db->table('examination_api_sync_states')->insert($stateData);
            } else {
                $this->db->table('examination_api_sync_states')->where('id', $state['id'])->update($stateData);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('The API delivery transaction failed.');
            }
            $this->db->transCommit();

            return [
                'deliveryId' => $deliveryId,
                'vendorCode' => $vendorCode,
                'deliveryMode' => $deliveryMode,
                'acknowledgementKey' => $acknowledgementKey,
                'acknowledgementStatus' => $returned > 0 ? 'pending' : 'acknowledged',
                'recordsSentThisCall' => $returned,
                'recordsDeliveredInCycle' => $cycleRecordCount,
                'pagesDeliveredInCycle' => $cyclePageCount,
                'totalRecordsDelivered' => $recordsDeliveredTotal,
                'firstRecordId' => $firstRecordId,
                'lastRecordId' => $lastRecordId,
                'responseLastUpdatedDate' => $responseLastUpdatedAt,
                'syncCompleted' => $syncCompleted,
                'nextPageNo' => $syncCompleted ? null : $pageNo + 1,
                'nextIncrementalDate' => $syncCompleted ? $completedCheckpoint : null,
            ];
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}

