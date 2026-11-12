<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\VendorActivityFilters;
use App\Services\VendorActivityDashboard;
use App\Services\VendorApiKeyService;
use InvalidArgumentException;

final class VendorActivityController extends BaseController
{
    public function index(): string
    {
        $filters = VendorActivityFilters::normalize(session()->get('vendor_activity_filters') ?? []);
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/vendors/activity', (new VendorActivityDashboard())->dashboard($filters) + [
            'title' => 'Vendor Activity', 'filters' => $filters, 'categories' => VendorApiKeyService::CATEGORIES,
        ]);
    }

    public function filter()
    {
        if ($this->request->getPost('clear_filters') !== null) {
            session()->remove('vendor_activity_filters');
        } else {
            try {
                $filters = VendorActivityFilters::normalize(array_replace(session()->get('vendor_activity_filters') ?? [], $this->request->getPost()));
                if ($filters['vendor_id'] !== null && db_connect()->table('api_vendors')->where('id', $filters['vendor_id'])->countAllResults() === 0) {
                    throw new InvalidArgumentException('Select an existing vendor.');
                }
                session()->set('vendor_activity_filters', $filters);
            } catch (InvalidArgumentException $exception) {
                return redirect()->to(url_to('admin.vendors.activity'))->with('error', $exception->getMessage());
            }
        }
        return redirect()->to(url_to('admin.vendors.activity'));
    }

    public function report()
    {
        $filters = VendorActivityFilters::normalize(session()->get('vendor_activity_filters') ?? []);
        $service = new VendorActivityDashboard();
        $rows = $service->requests($filters)->limit(VendorActivityDashboard::EXPORT_LIMIT + 1)->get()->getResultArray();
        if (count($rows) > VendorActivityDashboard::EXPORT_LIMIT) {
            return redirect()->to(url_to('admin.vendors.activity'))->with('error', 'This report exceeds 10,000 requests. Select a narrower date range or vendor.');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Request ID', 'Requested at', 'Vendor code', 'Vendor name', 'Category', 'Action', 'Method', 'HTTP status', 'Idempotency replay', 'Response time (ms)', 'Response bytes', 'IP address']);
        foreach ($rows as $row) {
            $values = [$row['id'], $row['requested_at'], $row['vendor_code'], $row['vendor_name'] ?? $row['vendor_code'], VendorApiKeyService::CATEGORIES[$row['category']] ?? $row['category'], $row['action'], $row['method'], $row['response_code'], (int) $row['is_replay'] === 1 ? 'Yes' : 'No', $row['duration_ms'], $row['response_bytes'], $row['request_ip']];
            // Spreadsheet applications must not execute vendor-controlled names as formulas.
            fputcsv($stream, array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value, $values));
        }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $this->response->download('vendor-activity-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.csv', $csv)->setContentType('text/csv')->setHeader('Cache-Control', 'no-store, private');
    }
}
