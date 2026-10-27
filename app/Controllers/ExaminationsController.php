<?php

namespace App\Controllers;

use App\Models\ExaminationModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

final class ExaminationsController extends BaseController
{
    public function index(): string
    {
        $model = new ExaminationModel();
        $filters = session()->get('examination_filters');
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
            $model->where('univ_aishe_code', $university);
        }
        if ($college !== '') {
            $model->where('college_study_center_code', $college);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('prn', $search)
                ->orLike('admission_form_no', $search)
                ->orLike('exam_form_number', $search)
                ->orLike('seat_number', $search)
                ->orLike('college_study_center_name', $search)
                ->orLike('course_name', $search)
                ->orLike('paper_name', $search)
                ->groupEnd();
        }

        $db = db_connect();
        $years = $db->table('examinations')->select('academic_year')->distinct()->orderBy('academic_year', 'DESC')->get()->getResultArray();
        $universities = $db->table('examinations')->select('univ_aishe_code, univ_name')->distinct()->orderBy('univ_name')->get()->getResultArray();
        $collegeRows = $db->table('examinations')
            ->select('univ_aishe_code, college_study_center_code, college_study_center_name')
            ->distinct()
            ->orderBy('college_study_center_name')
            ->orderBy('college_study_center_code')
            ->get()
            ->getResultArray();
        $colleges = [];
        foreach ($collegeRows as $row) {
            $code = (string) $row['college_study_center_code'];
            if (! isset($colleges[$code])) {
                $colleges[$code] = [
                    'college_code' => $code,
                    'college_name' => $row['college_study_center_name'],
                    'universities' => [],
                ];
            }
            $colleges[$code]['universities'][(string) $row['univ_aishe_code']] = true;
        }
        foreach ($colleges as &$collegeOption) {
            $collegeOption['universities'] = array_keys($collegeOption['universities']);
        }
        unset($collegeOption);

        return view('examinations/index', [
            'title' => 'Examination Data',
            'examinations' => $model->orderBy('updated_at', 'DESC')->paginate($perPage, 'examinations'),
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
            session()->remove('examination_filters');

            return redirect()->to(url_to('examinations.index'));
        }

        session()->set('examination_filters', [
            'academicYear' => trim((string) $this->request->getPost('academic_year')),
            'university' => trim((string) $this->request->getPost('univ_code')),
            'college' => trim((string) $this->request->getPost('college_code')),
            'search' => trim((string) $this->request->getPost('search')),
            'perPage' => $this->normalizeDataPageSize($this->request->getPost('per_page')),
        ]);

        return redirect()->to(url_to('examinations.index'));
    }

    public function show(int $id): string
    {
        $record = (new ExaminationModel())->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Examination record not found.');
        }

        return view('examinations/show', ['title' => 'Examination Record', 'record' => $record]);
    }
}

