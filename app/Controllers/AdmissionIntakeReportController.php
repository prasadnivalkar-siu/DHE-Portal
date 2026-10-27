<?php

namespace App\Controllers;

use App\Services\AdmissionIntakeReportService;
use CodeIgniter\HTTP\RedirectResponse;

final class AdmissionIntakeReportController extends BaseController
{
    private const FILTER_SESSION_KEY = 'admission_intake_report_filters';
    private const STATUSES = ['all', 'matched', 'missing_intake', 'no_admissions'];

    public function index(): string
    {
        $service = new AdmissionIntakeReportService();
        $options = $service->options();
        $storedFilters = session()->get(self::FILTER_SESSION_KEY);
        $storedFilters = is_array($storedFilters) ? $storedFilters : [];
        $academicYear = array_key_exists('academicYear', $storedFilters)
            ? trim((string) $storedFilters['academicYear'])
            : (string) ($options['years'][0] ?? '');
        $institution = trim((string) ($storedFilters['institution'] ?? ''));
        $search = trim((string) ($storedFilters['search'] ?? ''));
        $status = in_array($storedFilters['status'] ?? 'all', self::STATUSES, true) ? (string) ($storedFilters['status'] ?? 'all') : 'all';
        $perPage = $this->normalizeDataPageSize($storedFilters['perPage'] ?? null);
        $page = max(1, (int) ($storedFilters['page'] ?? 1));
        $filters = compact('academicYear', 'institution', 'search', 'status', 'perPage', 'page');
        $rows = $service->rows($filters);
        $totalRows = count($rows);
        $pageCount = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $pageCount);
        $filters['page'] = $page;

        $totals = $this->totals($rows);
        $institutionTotals = $this->institutionTotals($rows);
        $pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return view('intake/admission_summary', [
            'title' => 'Intake and Admissions Summary',
            'rows' => $pageRows,
            'totals' => $totals,
            'institutionTotals' => $institutionTotals,
            'years' => $options['years'],
            'institutions' => $options['institutions'],
            'filters' => $filters,
            'pageSizeOptions' => self::DATA_PAGE_SIZE_OPTIONS,
            'totalRows' => $totalRows,
            'pageCount' => $pageCount,
        ]);
    }

    public function filter(): RedirectResponse
    {
        if ($this->request->getPost('clear_filters') !== null) {
            session()->remove(self::FILTER_SESSION_KEY);

            return redirect()->to(url_to('intake.admission_summary'));
        }

        $status = trim((string) $this->request->getPost('status'));
        session()->set(self::FILTER_SESSION_KEY, [
            'academicYear' => trim((string) $this->request->getPost('academic_year')),
            'institution' => trim((string) $this->request->getPost('institution')),
            'search' => trim((string) $this->request->getPost('search')),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
            'perPage' => $this->normalizeDataPageSize($this->request->getPost('per_page')),
            'page' => max(1, (int) ($this->request->getPost('page') ?: 1)),
        ]);

        return redirect()->to(url_to('intake.admission_summary'));
    }

    /** @param list<array<string, mixed>> $rows @return array<string, int|float|null> */
    private function totals(array $rows): array
    {
        $admissions = 0;
        $intake = 0;
        $institutions = [];
        $matched = 0;
        $missingIntake = 0;
        $noAdmissions = 0;
        foreach ($rows as $row) {
            $admissions += (int) $row['admission_count'];
            $intake += (int) ($row['intake_total'] ?? 0);
            $institutions[$row['institution_group_key']] = true;
            $matched += $row['match_status'] === 'matched' ? 1 : 0;
            $missingIntake += $row['match_status'] === 'missing_intake' ? 1 : 0;
            $noAdmissions += $row['match_status'] === 'no_admissions' ? 1 : 0;
        }

        return [
            'admissions' => $admissions,
            'intake' => $intake,
            'available' => $intake - $admissions,
            'utilization' => $intake > 0 ? round(($admissions / $intake) * 100, 1) : null,
            'institutions' => count($institutions),
            'matched' => $matched,
            'missingIntake' => $missingIntake,
            'noAdmissions' => $noAdmissions,
        ];
    }

    /** @param list<array<string, mixed>> $rows @return array<string, array{intake: int, admissions: int, available: int}> */
    private function institutionTotals(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $key = (string) $row['institution_group_key'];
            if (! isset($totals[$key])) {
                $totals[$key] = ['intake' => 0, 'admissions' => 0, 'available' => 0];
            }
            $totals[$key]['intake'] += (int) ($row['intake_total'] ?? 0);
            $totals[$key]['admissions'] += (int) $row['admission_count'];
            $totals[$key]['available'] = $totals[$key]['intake'] - $totals[$key]['admissions'];
        }

        return $totals;
    }
}
