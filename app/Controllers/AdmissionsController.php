<?php

namespace App\Controllers;

use App\Models\AdmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class AdmissionsController extends BaseController
{
    public function index(): string
    {
        $model = new AdmissionModel();
        $filters = session()->get('admission_filters');
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
            $model->where('univ_code', $university);
        }
        if ($college !== '') {
            $model->where('college_code', $college);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('prn', $search)
                ->orLike('student_id', $search)
                ->orLike('admission_application_form_no', $search)
                ->orLike('college_name', $search)
                ->orLike('program', $search)
                ->groupEnd();
        }

        $db = db_connect();
        $years = $db->table('admissions')->select('academic_year')->distinct()->orderBy('academic_year', 'DESC')->get()->getResultArray();
        $universities = $db->table('admissions')->select('univ_code, univ_name')->distinct()->orderBy('univ_name')->get()->getResultArray();
        $collegeRows = $db->table('admissions')
            ->select('univ_code, college_code, college_name')
            ->distinct()
            ->orderBy('college_name')
            ->orderBy('college_code')
            ->get()
            ->getResultArray();
        $colleges = [];
        foreach ($collegeRows as $row) {
            $code = (string) $row['college_code'];
            if (! isset($colleges[$code])) {
                $colleges[$code] = [
                    'college_code' => $code,
                    'college_name' => $row['college_name'],
                    'universities' => [],
                ];
            }
            $colleges[$code]['universities'][(string) $row['univ_code']] = true;
        }
        foreach ($colleges as &$collegeOption) {
            $collegeOption['universities'] = array_keys($collegeOption['universities']);
        }
        unset($collegeOption);

        return view('admissions/index', [
            'title' => 'Admission Data',
            'admissions' => $model->orderBy('updated_at', 'DESC')->paginate($perPage, 'admissions'),
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
            session()->remove('admission_filters');

            return redirect()->to(url_to('admissions.index'));
        }

        session()->set('admission_filters', [
            'academicYear' => trim((string) $this->request->getPost('academic_year')),
            'university' => trim((string) $this->request->getPost('univ_code')),
            'college' => trim((string) $this->request->getPost('college_code')),
            'search' => trim((string) $this->request->getPost('search')),
            'perPage' => $this->normalizeDataPageSize($this->request->getPost('per_page')),
        ]);

        return redirect()->to(url_to('admissions.index'));
    }

    public function show(int $id): string
    {
        $record = (new AdmissionModel())->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Admission record not found.');
        }

        return view('admissions/show', ['title' => 'Admission Record', 'record' => $record]);
    }
}
