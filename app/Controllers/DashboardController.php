<?php

namespace App\Controllers;

use App\Models\AdmissionImportJobModel;
use App\Models\AdmissionModel;
use App\Models\ExaminationImportJobModel;
use App\Models\ExaminationModel;
use App\Models\ExpenseImportJobModel;
use App\Models\ExpenseModel;
use App\Models\IntakeImportJobModel;
use App\Models\IntakeModel;
use App\Models\PlacementImportJobModel;
use App\Models\PlacementModel;
use App\Models\ResultImportJobModel;
use App\Models\ResultModel;

final class DashboardController extends BaseController
{
    public function index(): string
    {
        $admissions = new AdmissionModel();
        $jobs = new AdmissionImportJobModel();
        $examinations = new ExaminationModel();
        $examinationJobs = new ExaminationImportJobModel();
        $intakes = new IntakeModel();
        $intakeJobs = new IntakeImportJobModel();
        $placements = new PlacementModel();
        $placementJobs = new PlacementImportJobModel();
        $expenses = new ExpenseModel();
        $expenseJobs = new ExpenseImportJobModel();
        $results = new ResultModel();
        $resultJobs = new ResultImportJobModel();
        $db = db_connect();

        $stats = [
            'records' => $admissions->countAllResults(),
            'universities' => $db->table('admissions')->select('univ_code')->distinct()->countAllResults(),
            'latest_update' => $db->table('admissions')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];
        $examinationStats = [
            'records' => $examinations->countAllResults(),
            'universities' => $db->table('examinations')->select('univ_aishe_code')->distinct()->countAllResults(),
            'latest_update' => $db->table('examinations')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];
        $intakeStats = [
            'records' => $intakes->countAllResults(),
            'universities' => $db->table('intakes')->select('university_aishe_code')->distinct()->countAllResults(),
            'latest_update' => $db->table('intakes')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];
        $placementStats = [
            'records' => $placements->countAllResults(),
            'universities' => $db->table('placements')->select('university_aishe_code')->distinct()->countAllResults(),
            'companies' => $db->table('placements')->select('company_name')->distinct()->countAllResults(),
            'latest_update' => $db->table('placements')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];
        $expenseStats = [
            'records' => $expenses->countAllResults(),
            'universities' => $db->table('expenses')->select('university_aishe_code')->distinct()->countAllResults(),
            'latest_update' => $db->table('expenses')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];
        $resultStats = [
            'records' => $results->countAllResults(),
            'universities' => $db->table('results')->select('university_aishe_code')->distinct()->countAllResults(),
            'latest_update' => $db->table('results')->selectMax('updated_at')->get()->getRowArray()['updated_at'] ?? null,
        ];

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentImports' => auth()->user()->can('imports.view')
                ? $jobs->orderBy('id', 'DESC')->findAll(5)
                : [],
            'examinationStats' => $examinationStats,
            'recentExaminationImports' => auth()->user()->can('examination_imports.view')
                ? $examinationJobs->orderBy('id', 'DESC')->findAll(5)
                : [],
            'intakeStats' => $intakeStats,
            'recentIntakeImports' => auth()->user()->can('intake_imports.view')
                ? $intakeJobs->orderBy('id', 'DESC')->findAll(5)
                : [],
            'placementStats' => $placementStats,
            'recentPlacementImports' => auth()->user()->can('placement_imports.view')
                ? $placementJobs->orderBy('id', 'DESC')->findAll(5)
                : [],
            'expenseStats' => $expenseStats,
            'recentExpenseImports' => auth()->user()->can('expense_imports.view')
                ? $expenseJobs->orderBy('id', 'DESC')->findAll(5)
                : [],
            'resultStats' => $resultStats,
            'recentResultImports' => auth()->user()->can('result_imports.view')
                ? $resultJobs->orderBy('id', 'DESC')->findAll(5)
                : [],
        ]);
    }
}
