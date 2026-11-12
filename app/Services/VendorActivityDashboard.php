<?php

namespace App\Services;

use App\Libraries\VendorImportSchema;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

final class VendorActivityDashboard
{
    public const PAGE_SIZE = 25;
    public const EXPORT_LIMIT = 10000;
    private BaseConnection $db;
    private const COUNTS = ['requests', 'successful', 'failed', 'replays', 'rate_limited', 'denied', 'duration_total', 'sent', 'acknowledged', 'pending', 'delivery_failed', 'deliveries', 'batches', 'accepted', 'rejected', 'duplicates', 'draft', 'validated', 'approved', 'published'];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function vendors(): array
    {
        return $this->db->table('api_vendors')->select('id, vendor_code, name, is_active, last_used_at')->orderBy('name')->get()->getResultArray();
    }

    public function dashboard(array $filters): array
    {
        $vendors = $this->vendors();
        $vendorCode = null;
        $vendorMap = [];
        foreach ($vendors as $vendor) {
            $vendorMap[$vendor['vendor_code']] = $vendor;
            if ((int) $vendor['id'] === $filters['vendor_id']) {
                $vendorCode = $vendor['vendor_code'];
            }
        }
        $totals = array_fill_keys(self::COUNTS, 0);
        $categoryRows = [];
        foreach (VendorApiKeyService::CATEGORIES as $key => $label) {
            if ($filters['category'] === '' || $filters['category'] === $key) {
                $categoryRows[$key] = ['category' => $key, 'label' => $label] + $totals;
            }
        }
        $vendorRows = [];
        $add = static function (string $code, string $category, array $metrics) use (&$totals, &$categoryRows, &$vendorRows, $vendorMap): void {
            $vendorRows[$code] ??= ($vendorMap[$code] ?? ['id' => null, 'name' => $code, 'is_active' => null, 'last_used_at' => null]) + ['vendor_code' => $code] + array_fill_keys(self::COUNTS, 0);
            foreach ($metrics as $key => $value) {
                $totals[$key] += (int) $value;
                $categoryRows[$category][$key] += (int) $value;
                $vendorRows[$code][$key] += (int) $value;
            }
        };
        $groups = $this->requestRange($this->db->table('vendor_api_activity'), $filters)
            ->select('vendor_code, category')
            ->select('COUNT(*) AS requests, SUM(response_code >= 200 AND response_code < 300) AS successful, SUM(response_code >= 400) AS failed, SUM(is_replay) AS replays, SUM(response_code = 429) AS rate_limited, SUM(response_code = 403) AS denied, SUM(duration_ms) AS duration_total', false)
            ->groupBy(['vendor_code', 'category'])->get()->getResultArray();
        foreach ($groups as $group) {
            $code = $group['vendor_code']; $category = $group['category'];
            unset($group['vendor_code'], $group['category']);
            if (isset($categoryRows[$category])) { $add($code, $category, $group); }
        }
        foreach ($categoryRows as $category => $_) {
            [, $prefix] = VendorImportSchema::definition($category);
            $delivery = $this->dateRange($this->db->table($prefix . '_api_logs'), $filters, 'requested_at');
            if ($filters['vendor_id'] !== null) { $delivery->where('vendor_code', $vendorCode ?? ''); }
            $deliveryGroups = $delivery->select('vendor_code')
                ->select('COUNT(*) AS deliveries, SUM(CASE WHEN response_code = 200 THEN records_returned ELSE 0 END) AS sent, SUM(CASE WHEN response_code = 200 AND ack_status = \'acknowledged\' THEN records_returned ELSE 0 END) AS acknowledged, SUM(CASE WHEN response_code = 200 AND ack_status = \'pending\' THEN records_returned ELSE 0 END) AS pending, SUM(CASE WHEN response_code = 200 AND ack_status = \'failed\' THEN records_returned ELSE 0 END) AS delivery_failed', false)
                ->groupBy('vendor_code')->get()->getResultArray();
            foreach ($deliveryGroups as $group) { $code = $group['vendor_code']; unset($group['vendor_code']); $add($code, $category, $group); }
            $imports = $this->dateRange($this->db->table('vendor_api_imports b'), $filters, 'b.created_at')->where('b.category', $category);
            if ($filters['vendor_id'] !== null) { $imports->where('b.vendor_id', $filters['vendor_id']); }
            $importGroups = $imports->join($prefix . '_import_jobs j', 'j.id = b.import_job_id')->select('b.vendor_code')
                ->select('COUNT(*) AS batches, SUM(j.valid_rows) AS accepted, SUM(j.rejected_rows) AS rejected, SUM(j.duplicate_rows) AS duplicates, SUM(j.workflow_status = \'draft\') AS draft, SUM(j.workflow_status = \'validated\') AS validated, SUM(j.workflow_status = \'approved\') AS approved, SUM(j.workflow_status = \'published\') AS published', false)
                ->groupBy('b.vendor_code')->get()->getResultArray();
            foreach ($importGroups as $group) { $code = $group['vendor_code']; unset($group['vendor_code']); $add($code, $category, $group); }
        }
        usort($vendorRows, static fn ($a, $b) => [$b['requests'], $b['sent'], $b['batches'], $a['vendor_code']] <=> [$a['requests'], $a['sent'], $a['batches'], $b['vendor_code']]);
        $requestTotal = (int) $this->requestRange($this->db->table('vendor_api_activity'), $filters)->countAllResults();
        $pages = max(1, (int) ceil($requestTotal / self::PAGE_SIZE));
        $page = min($pages, $filters['page']);
        $requests = $this->requests($filters)->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)->get()->getResultArray();
        $daily = $this->requestRange($this->db->table('vendor_api_activity'), $filters)
            ->select('DATE(requested_at) AS day, COUNT(*) AS requests, SUM(response_code >= 400) AS failed', false)
            ->groupBy('DATE(requested_at)', false)->orderBy('day')->get()->getResultArray();
        return compact('vendors', 'totals', 'categoryRows', 'vendorRows', 'requests', 'requestTotal', 'page', 'pages') + [
            'trend' => self::trend($daily, $filters['date_from'], $filters['date_to']),
            'trackingStartedAt' => $this->db->table('vendor_api_activity')->selectMin('requested_at')->get()->getRowArray()['requested_at'] ?? null,
            'activeVendors' => count(array_filter($vendors, static fn ($vendor) => (int) $vendor['is_active'] === 1)),
        ];
    }

    public function requests(array $filters): BaseBuilder
    {
        return $this->requestRange($this->db->table('vendor_api_activity a'), $filters, 'a.')
            ->select('a.id, a.vendor_id, a.vendor_code, v.name AS vendor_name, a.category, a.action, a.method, a.response_code, a.is_replay, a.duration_ms, a.response_bytes, a.request_ip, a.requested_at')
            ->join('api_vendors v', 'v.id = a.vendor_id', 'left')->orderBy('a.requested_at', 'DESC')->orderBy('a.id', 'DESC');
    }

    private function requestRange(BaseBuilder $builder, array $filters, string $alias = ''): BaseBuilder
    {
        $this->dateRange($builder, $filters, $alias . 'requested_at');
        if ($filters['vendor_id'] !== null) { $builder->where($alias . 'vendor_id', $filters['vendor_id']); }
        if ($filters['category'] !== '') { $builder->where($alias . 'category', $filters['category']); }
        return $builder;
    }

    private function dateRange(BaseBuilder $builder, array $filters, string $field): BaseBuilder
    {
        return $builder->where($field . ' >=', $filters['date_from'] . ' 00:00:00')
            ->where($field . ' <', (new DateTimeImmutable($filters['date_to']))->modify('+1 day')->format('Y-m-d 00:00:00'));
    }

    /** Daily for short ranges, calendar weeks for longer ranges; include days with no traffic. */
    public static function trend(array $daily, string $from, string $to): array
    {
        $start = new DateTimeImmutable($from); $end = new DateTimeImmutable($to);
        $weekly = (int) $start->diff($end)->days > 30;
        $rows = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $key = $weekly ? $day->modify('monday this week')->format('Y-m-d') : $day->format('Y-m-d');
            $rows[$key] ??= ['day' => $key, 'label' => ($weekly ? 'Week of ' : '') . (new DateTimeImmutable($key))->format('d M'), 'requests' => 0, 'failed' => 0];
        }
        foreach ($daily as $row) {
            $day = new DateTimeImmutable($row['day']);
            $key = $weekly ? $day->modify('monday this week')->format('Y-m-d') : $row['day'];
            if (isset($rows[$key])) { $rows[$key]['requests'] += (int) $row['requests']; $rows[$key]['failed'] += (int) $row['failed']; }
        }
        return array_values($rows);
    }
}
