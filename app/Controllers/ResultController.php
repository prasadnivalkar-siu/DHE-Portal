<?php

namespace App\Controllers;

use App\Libraries\ResultSchema;
use App\Models\ResultModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class ResultController extends BaseController
{
    public function index(): string
    {
        $model = new ResultModel();
        $filters = session()->get('result_filters');
        $filters = is_array($filters) ? $filters : [];
        $academicYear = trim((string) ($filters['academicYear'] ?? ''));
        $examYear = trim((string) ($filters['examYear'] ?? ''));
        $season = trim((string) ($filters['season'] ?? ''));
        $university = trim((string) ($filters['university'] ?? ''));
        $organization = trim((string) ($filters['organization'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = $this->normalizeDataPageSize($filters['perPage'] ?? null);

        if ($academicYear !== '') {
            $model->where('academic_year', $academicYear);
        }
        if ($university !== '') {
            $model->where('university_aishe_code', $university);
        }
        if ($examYear !== '') {
            $model->where('exam_year', $examYear);
        }
        if ($season !== '') {
            $model->where('season', $season);
        }
        if ($organization !== '') {
            $model->where('organization_aishe_code', $organization);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('organization_aishe_code', $search)
                ->orLike('organization_code', $search)
                ->orLike('organization_name', $search)
                ->orLike('course_name', $search)
                ->orLike('course_code', $search)
                ->orLike('prn', $search)
                ->orLike('registration_no', $search)
                ->orLike('roll_no', $search)
                ->groupEnd();
        }

        $db = db_connect();
        $years = $db->table('results')->select('academic_year')->distinct()->orderBy('academic_year', 'DESC')->get()->getResultArray();
        $examYears = $db->table('results')->select('exam_year')->distinct()->orderBy('exam_year', 'DESC')->get()->getResultArray();
        $seasons = $db->table('results')->select('season')->distinct()->orderBy('season')->get()->getResultArray();
        $universities = $db->table('results')->select('university_aishe_code, university_name')->distinct()->orderBy('university_name')->get()->getResultArray();
        $organizationRows = $db->table('results')
            ->select('university_aishe_code, organization_aishe_code, organization_name')
            ->distinct()
            ->orderBy('organization_name')
            ->orderBy('organization_aishe_code')
            ->get()
            ->getResultArray();
        $organizations = [];
        foreach ($organizationRows as $row) {
            $code = (string) $row['organization_aishe_code'];
            if (! isset($organizations[$code])) {
                $organizations[$code] = [
                    'organization_code' => $code,
                    'organization_name' => $row['organization_name'],
                    'universities' => [],
                ];
            }
            $organizations[$code]['universities'][(string) $row['university_aishe_code']] = true;
        }
        foreach ($organizations as &$organizationOption) {
            $organizationOption['universities'] = array_keys($organizationOption['universities']);
        }
        unset($organizationOption);

        return view('results/index', [
            'title' => 'Result Data',
            'results' => $model->orderBy('updated_at', 'DESC')->paginate($perPage, 'results'),
            'pager' => $model->pager,
            'pageSizeOptions' => self::DATA_PAGE_SIZE_OPTIONS,
            'years' => $years,
            'examYears' => $examYears,
            'seasons' => $seasons,
            'universities' => $universities,
            'organizations' => array_values($organizations),
            'filters' => compact('academicYear', 'examYear', 'season', 'university', 'organization', 'search', 'perPage'),
        ]);
    }

    public function filter(): RedirectResponse
    {
        if ($this->request->getPost('clear_filters') !== null) {
            session()->remove('result_filters');

            return redirect()->to(url_to('results.index'));
        }

        session()->set('result_filters', [
            'academicYear' => trim((string) $this->request->getPost('academic_year')),
            'examYear' => trim((string) $this->request->getPost('exam_year')),
            'season' => trim((string) $this->request->getPost('season')),
            'university' => trim((string) $this->request->getPost('univ_code')),
            'organization' => trim((string) $this->request->getPost('organization_code')),
            'search' => trim((string) $this->request->getPost('search')),
            'perPage' => $this->normalizeDataPageSize($this->request->getPost('per_page')),
        ]);

        return redirect()->to(url_to('results.index'));
    }

    public function show(int $id): string
    {
        $record = (new ResultModel())->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Result record not found.');
        }

        $payload = ResultSchema::toApi($record);
        $headers = ResultSchema::headers();
        $subjectSections = [];
        for ($subject = 1; $subject <= 15; $subject++) {
            $subjectSections['Subject ' . $subject] = array_slice($headers, 79 + (($subject - 1) * 24), 24);
        }

        return view('results/show', [
            'title' => 'Result Record',
            'record' => $record,
            'payload' => $payload,
            'generalHeaders' => array_merge(array_slice($headers, 0, 79), ['LAST_UPDATE_DATE']),
            'subjectSections' => $subjectSections,
        ]);
    }
}



