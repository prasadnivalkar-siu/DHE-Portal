<?php

namespace App\Controllers;

use App\Models\PlacementModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class PlacementController extends BaseController
{
    public function index(): string
    {
        $model = new PlacementModel();
        $filters = session()->get('placement_filters');
        $filters = is_array($filters) ? $filters : [];
        $academicYear = trim((string) ($filters['academicYear'] ?? ''));
        $university = trim((string) ($filters['university'] ?? ''));
        $college = trim((string) ($filters['college'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = $this->normalizeDataPageSize($filters['perPage'] ?? null);

        if ($academicYear !== '') {
            $model->where('academic_year', $academicYear);
        }
        if ($university !== '') {
            $model->where('university_aishe_code', $university);
        }
        if ($college !== '') {
            $model->where('institution_aishe_code', $college);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('institution_aishe_code', $search)
                ->orLike('institution_name', $search)
                ->orLike('institution_type_name', $search)
                ->orLike('program_name', $search)
                ->orLike('course_name', $search)
                ->orLike('company_name', $search)
                ->groupEnd();
        }

        $db = db_connect();
        $years = $db->table('placements')->select('academic_year')->distinct()->orderBy('academic_year', 'DESC')->get()->getResultArray();
        $universities = $db->table('placements')->select('university_aishe_code, university_name')->distinct()->orderBy('university_name')->get()->getResultArray();
        $collegeRows = $db->table('placements')
            ->select('university_aishe_code, institution_aishe_code, institution_name')
            ->distinct()
            ->orderBy('institution_name')
            ->orderBy('institution_aishe_code')
            ->get()
            ->getResultArray();
        $colleges = [];
        foreach ($collegeRows as $row) {
            $code = (string) $row['institution_aishe_code'];
            if (! isset($colleges[$code])) {
                $colleges[$code] = [
                    'college_code' => $code,
                    'college_name' => $row['institution_name'],
                    'universities' => [],
                ];
            }
            $colleges[$code]['universities'][(string) $row['university_aishe_code']] = true;
        }
        foreach ($colleges as &$collegeOption) {
            $collegeOption['universities'] = array_keys($collegeOption['universities']);
        }
        unset($collegeOption);

        return view('placements/index', [
            'title' => 'Placement Data',
            'placements' => $model->orderBy('updated_at', 'DESC')->paginate($perPage, 'placements'),
            'pager' => $model->pager,
            'pageSizeOptions' => self::DATA_PAGE_SIZE_OPTIONS,
            'years' => $years,
            'universities' => $universities,
            'colleges' => array_values($colleges),
            'filters' => compact('academicYear', 'university', 'college', 'search', 'perPage'),
        ]);
    }

    public function filter(): RedirectResponse
    {
        if ($this->request->getPost('clear_filters') !== null) {
            session()->remove('placement_filters');

            return redirect()->to(url_to('placements.index'));
        }

        session()->set('placement_filters', [
            'academicYear' => trim((string) $this->request->getPost('academic_year')),
            'university' => trim((string) $this->request->getPost('univ_code')),
            'college' => trim((string) $this->request->getPost('college_code')),
            'search' => trim((string) $this->request->getPost('search')),
            'perPage' => $this->normalizeDataPageSize($this->request->getPost('per_page')),
        ]);

        return redirect()->to(url_to('placements.index'));
    }

    public function show(int $id): string
    {
        $record = (new PlacementModel())->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Placement record not found.');
        }

        return view('placements/show', ['title' => 'Placement Record', 'record' => $record]);
    }
}


