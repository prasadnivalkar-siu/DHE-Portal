<?php

namespace App\Controllers;

use App\Services\AdminAuditService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

final class ApiDocumentationController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();

        return view('api/documentation', [
            'title' => 'API Documentation',
            'syncStates' => $db->table('admission_api_sync_states')->orderBy('last_delivery_at', 'DESC')->limit(20)->get()->getResultArray(),
            'vendors' => array_column(
                $db->table('admission_api_logs')
                    ->select('vendor_code')
                    ->where('cycle_key IS NOT NULL', null, false)
                    ->distinct()
                    ->orderBy('vendor_code')
                    ->get()
                    ->getResultArray(),
                'vendor_code',
            ),
            'recentDeliveries' => $db->table('admission_api_logs')
                ->where('response_code', 200)
                ->where('cycle_key IS NOT NULL', null, false)
                ->orderBy('id', 'DESC')
                ->limit(25)
                ->get()
                ->getResultArray(),
            'deliveryStats' => $db->table('admission_api_logs')
                ->select('COALESCE(SUM(CASE WHEN response_code = 200 THEN records_returned ELSE 0 END), 0) AS sent_records', false)
                ->select("COALESCE(SUM(CASE WHEN ack_status = 'acknowledged' THEN records_returned ELSE 0 END), 0) AS acknowledged_records", false)
                ->select("COALESCE(SUM(CASE WHEN ack_status = 'pending' THEN records_returned ELSE 0 END), 0) AS pending_records", false)
                ->select("COALESCE(SUM(CASE WHEN ack_status = 'failed' THEN records_returned ELSE 0 END), 0) AS failed_records", false)
                ->select('COALESCE(SUM(CASE WHEN response_code != 200 THEN 1 ELSE 0 END), 0) AS failed_requests', false)
                ->get()->getRowArray(),
        ]);
    }

    public function deliveryReport(): ResponseInterface
    {
        $vendorCode = trim((string) $this->request->getPost('vendor_code'));
        $from = trim((string) $this->request->getPost('from_date'));
        $to = trim((string) $this->request->getPost('to_date'));
        if ($vendorCode !== '' && (strlen($vendorCode) > 100 || preg_match('/^[a-zA-Z0-9._-]+$/', $vendorCode) !== 1)) {
            return redirect()->to(url_to('api.documentation'))->with('error', 'Select a valid vendor.');
        }
        foreach (['from date' => $from, 'to date' => $to] as $label => $date) {
            if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                return redirect()->to(url_to('api.documentation'))->with('error', 'Enter a valid ' . $label . '.');
            }
        }

        $query = db_connect()->table('admission_api_logs')->orderBy('id', 'DESC');
        if ($vendorCode !== '') {
            $query->where('vendor_code', $vendorCode);
        }
        if ($from !== '') {
            $query->where('requested_at >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $query->where('requested_at <=', $to . ' 23:59:59');
        }
        $deliveries = $query->get()->getResultArray();
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Delivery Report');
        $headers = ['Delivery ID', 'Vendor', 'Mode', 'Academic Year', 'University', 'Page', 'Records Sent', 'First Record', 'Last Record', 'HTTP Status', 'Acknowledgment', 'Acknowledged At', 'Requested At', 'Last Updated Watermark', 'Redelivery Record', 'Redelivery Date', 'Request IP'];
        $sheet->fromArray($headers, null, 'A1');
        $line = 2;
        foreach ($deliveries as $delivery) {
            $sheet->fromArray([
                (int) $delivery['id'], $delivery['vendor_code'], $delivery['delivery_mode'] ?? 'standard', $delivery['academic_year'], $delivery['university_aishe_code'],
                (int) $delivery['page_no'], (int) $delivery['records_returned'], $delivery['response_first_record_id'], $delivery['response_last_record_id'],
                (int) $delivery['response_code'], $delivery['ack_status'] ?? '—', $delivery['acknowledged_at'] ?? null, $delivery['requested_at'],
                $delivery['response_last_updated_at'] ?? null, $delivery['redelivery_from_record_id'] ?? null, $delivery['redelivery_from_date'] ?? null, $delivery['request_ip'],
            ], null, 'A' . $line++);
        }
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:Q' . max(1, $line - 1));
        foreach (range('A', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $temporary = tempnam(WRITEPATH . 'cache', 'delivery-report-');
        (new Xlsx($book))->save($temporary);
        $book->disconnectWorksheets();
        $contents = (string) file_get_contents($temporary);
        @unlink($temporary);
        (new AdminAuditService())->record('api.delivery_report_downloaded', null, ['vendor_code' => $vendorCode ?: 'all', 'rows' => count($deliveries), 'from' => $from, 'to' => $to]);
        return $this->response->download('admission-api-delivery-report-' . date('Ymd-His') . '.xlsx', $contents, true);
    }

    public function resetDeliveries(): RedirectResponse
    {
        $scope = trim((string) $this->request->getPost('reset_scope'));
        $vendorCode = trim((string) $this->request->getPost('vendor_code'));
        $confirmed = $this->request->getPost('confirm_reset') === '1';

        if (! $confirmed) {
            return redirect()->to(url_to('api.documentation'))->with('error', 'Confirm the delivery reset before continuing.');
        }
        if (! in_array($scope, ['details', 'all'], true)) {
            return redirect()->to(url_to('api.documentation'))->with('error', 'Select a valid reset scope.');
        }
        if ($vendorCode !== '' && (strlen($vendorCode) > 100 || preg_match('/^[a-zA-Z0-9._-]+$/', $vendorCode) !== 1)) {
            return redirect()->to(url_to('api.documentation'))->with('error', 'Select a valid vendor.');
        }

        $db = db_connect();
        $deliveryCountBuilder = $db->table('admission_api_delivery_records r')
            ->join('admission_api_logs l', 'l.id = r.api_log_id')
            ->where('l.cycle_key IS NOT NULL', null, false);
        $activeCycleGuard = 'NOT EXISTS (SELECT 1 FROM admission_api_sync_states s'
            . ' WHERE s.vendor_code = l.vendor_code'
            . ' AND s.academic_year = l.academic_year'
            . ' AND s.university_aishe_code = l.university_aishe_code'
            . ' AND s.current_cycle_key = l.cycle_key'
            . ' AND s.sync_completed = 0)';
        if ($scope === 'details') {
            $deliveryCountBuilder->where($activeCycleGuard, null, false);
        }
        $logCountBuilder = $db->table('admission_api_logs')->where('cycle_key IS NOT NULL', null, false);
        $stateCountBuilder = $db->table('admission_api_sync_states');
        $idempotencyCountBuilder = $db->table('admission_api_idempotency');
        if ($vendorCode !== '') {
            $deliveryCountBuilder->where('l.vendor_code', $vendorCode);
            $logCountBuilder->where('vendor_code', $vendorCode);
            $stateCountBuilder->where('vendor_code', $vendorCode);
            $idempotencyCountBuilder->where('vendor_code', $vendorCode);
        }
        $deliveryCount = $deliveryCountBuilder->countAllResults();
        $logCount = $logCountBuilder->countAllResults();
        $stateCount = $stateCountBuilder->countAllResults();
        $idempotencyCount = $scope === 'all' ? $idempotencyCountBuilder->countAllResults() : 0;

        $db->transBegin();
        try {
            if ($scope === 'all') {
                $states = $db->table('admission_api_sync_states');
                if ($vendorCode !== '') {
                    $states->where('vendor_code', $vendorCode);
                }
                $states->delete();
            }

            $sql = 'DELETE r FROM admission_api_delivery_records r INNER JOIN admission_api_logs l ON l.id = r.api_log_id WHERE l.cycle_key IS NOT NULL';
            $params = [];
            if ($scope === 'details') {
                $sql .= ' AND ' . $activeCycleGuard;
            }
            if ($vendorCode !== '') {
                $sql .= ' AND l.vendor_code = ?';
                $params[] = $vendorCode;
            }
            $db->query($sql, $params);

            if ($scope === 'all') {
                $logs = $db->table('admission_api_logs')->where('cycle_key IS NOT NULL', null, false);
                if ($vendorCode !== '') {
                    $logs->where('vendor_code', $vendorCode);
                }
                $logs->delete();

                $idempotency = $db->table('admission_api_idempotency');
                if ($vendorCode !== '') {
                    $idempotency->where('vendor_code', $vendorCode);
                }
                $idempotency->delete();
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('The delivery reset transaction failed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'API delivery reset failed: {message}', ['message' => $exception->getMessage()]);

            return redirect()->to(url_to('api.documentation'))->with('error', 'Delivery tracking could not be reset.');
        }

        try {
            $db->query('OPTIMIZE TABLE admission_api_delivery_records');
            if ($scope === 'all') {
                $db->query('OPTIMIZE TABLE admission_api_sync_states');
            }
        } catch (Throwable $exception) {
            log_message('warning', 'Delivery tables were reset but could not be optimized: {message}', ['message' => $exception->getMessage()]);
        }

        (new AdminAuditService())->record('api.delivery_tracking_reset', null, [
            'scope' => $scope,
            'vendor_code' => $vendorCode === '' ? 'all' : $vendorCode,
            'delivery_records_removed' => $deliveryCount,
            'delivery_logs_removed' => $scope === 'all' ? $logCount : 0,
            'sync_states_removed' => $scope === 'all' ? $stateCount : 0,
            'idempotency_records_removed' => $idempotencyCount,
        ]);

        $message = $scope === 'details'
            ? sprintf('Delivery history optimized: %s detailed record entries removed. Summary logs, checkpoints, and active-cycle records were preserved.', number_format($deliveryCount))
            : sprintf('Delivery tracking reset: %s record entries, %s summaries, %s sync states, and %s idempotency records removed. Admission data was not changed.', number_format($deliveryCount), number_format($logCount), number_format($stateCount), number_format($idempotencyCount));

        return redirect()->to(url_to('api.documentation'))->with('success', $message);
    }
}
