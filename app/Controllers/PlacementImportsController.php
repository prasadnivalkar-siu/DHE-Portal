<?php

namespace App\Controllers;

use App\Models\PlacementImportErrorModel;
use App\Models\PlacementImportJobModel;
use App\Libraries\PlacementSchema;
use App\Services\AdminAuditService;
use App\Services\PlacementDatasetWorkflowService;
use App\Services\PlacementImportDispatcher;
use App\Services\PlacementImportService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class PlacementImportsController extends BaseController
{
    private const STALLED_QUEUE_SECONDS = 10;
    private const STALLED_PREVIEW_SECONDS = 90;
    private const MAX_UPLOAD_KB = 102400;
    private const MAX_UPLOAD_BYTES = self::MAX_UPLOAD_KB * 1024;

    public function create(): string
    {
        return view('placements/import', [
            'title' => 'Import Placement Data',
            'progressToken' => bin2hex(random_bytes(16)),
            'maxUploadBytes' => self::MAX_UPLOAD_BYTES,
        ]);
    }

    public function store()
    {
        $file = $this->request->getFile('placement_file');
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->uploadFailure($file);
        }

        $rules = [
            'placement_file' => [
                'label' => 'Placement workbook',
                'rules' => 'uploaded[placement_file]|max_size[placement_file,' . self::MAX_UPLOAD_KB . ']|ext_in[placement_file,xlsx,xls]',
            ],
        ];
        if (! $this->validateData([], $rules)) {
            $errors = array_values($this->validator->getErrors());
            $message = $errors[0] ?? 'Please select a valid Excel workbook.';
            if ($this->request->isAJAX()) {
                return $this->importResponse(false, $message, 422, [
                    'errors' => $errors,
                ]);
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if (! $file->isValid()) {
            $message = 'The server could not verify the temporary upload. Check PHP upload_tmp_dir and its permissions.';
            if ($this->request->isAJAX()) {
                return $this->importResponse(false, $message, 500, ['uploadErrorCode' => $file->getError()]);
            }

            return redirect()->back()->with('error', $message);
        }

        $originalName = $file->getClientName();
        $progressToken = strtolower(trim((string) $this->request->getPost('progress_token')));
        if (preg_match('/^[a-f0-9]{32}$/', $progressToken) !== 1) {
            $progressToken = bin2hex(random_bytes(16));
        }
        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'placements' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            if ($this->request->isAJAX()) {
                return $this->importResponse(false, 'The upload directory could not be created.', 500);
            }

            return redirect()->back()->with('error', 'The upload directory could not be created.');
        }

        $storedName = $file->getRandomName();
        $file->move($directory, $storedName);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $storedName;
        $relativePath = ltrim(str_replace(WRITEPATH, '', $fullPath), '\\/');

        $jobId = null;
        try {
            $service = new PlacementImportService();
            $jobId = $service->createQueuedJob(
                $fullPath,
                $originalName,
                $relativePath,
                $progressToken,
                'queued_preview',
                (int) auth()->id(),
            );
            (new PlacementImportDispatcher())->dispatch($jobId, 'preview');
            (new AdminAuditService())->record('placements.import_uploaded', null, ['import_job_id' => $jobId, 'filename' => $originalName]);
            if ($this->request->isAJAX()) {

                return $this->importResponse(true, 'Placement workbook validation queued.', 202, [
                    'queued' => true,
                    'phase' => 'preview',
                    'jobId' => $jobId,
                    'redirect' => url_to('placements.logs.show', $jobId),
                ]);
            }

            return redirect()->to(url_to('placements.logs.show', $jobId))->with('success', 'Workbook uploaded. Validation is running in the background.');
        } catch (Throwable $exception) {
            if ($jobId !== null) {
                (new PlacementImportJobModel())->update($jobId, [
                    'status' => 'failed',
                    'failure_stage' => 'preview',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
                ]);
            }
            log_message('error', 'Placement import failed: {message}', ['message' => $exception->getMessage()]);
            if ($this->request->isAJAX()) {
                return $this->importResponse(false, 'Import failed: ' . $exception->getMessage(), 422, [
                    'redirect' => url_to('placements.logs'),
                ]);
            }

            return redirect()->to(url_to('placements.logs'))->with('error', 'Import failed: ' . $exception->getMessage());
        }
    }

    public function index(): string
    {
        $model = new PlacementImportJobModel();

        return view('placements/logs/index', [
            'title' => 'Import Logs',
            'jobs' => $model->orderBy('id', 'DESC')->paginate(25, 'imports'),
            'pager' => $model->pager,
        ]);
    }

    public function show(int $id): string
    {
        $job = (new PlacementImportJobModel())->find($id);
        if ($job === null) {
            throw PageNotFoundException::forPageNotFound('Import log not found.');
        }

        $errorModel = new PlacementImportErrorModel();

        return view('placements/logs/show', [
            'title' => 'Import Log #' . $id,
            'job' => $job,
            'errors' => $errorModel->where('import_job_id', $id)->orderBy('row_number')->paginate(50, 'errors'),
            'pager' => $errorModel->pager,
            'previewRows' => json_decode((string) ($job['preview_rows'] ?? '[]'), true) ?: [],
            'actors' => $this->actorNames($job),
        ]);
    }

    public function confirm(int $id)
    {
        try {
            $db = db_connect();
            $updated = $db->table('placement_import_jobs')
                ->where('id', $id)
                ->where('status', 'preview_ready')
                ->update([
                    'status' => 'queued',
                    'inserted_rows' => 0,
                    'updated_rows' => 0,
                    'skipped_rows' => 0,
                    'error_rows' => 0,
                    'completed_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if (! $updated || $db->affectedRows() !== 1) {
                throw new RuntimeException('Only a validated preview can be confirmed for import.');
            }
            (new PlacementImportDispatcher())->dispatch($id, 'import');
            (new AdminAuditService())->record('placements.import_confirmed', null, ['import_job_id' => $id]);
            return redirect()->to(url_to('placements.logs.show', $id))->with('success', 'Database import queued. This page will show its progress.');
        } catch (Throwable $exception) {
            db_connect()->table('placement_import_jobs')->where('id', $id)->where('status', 'queued')->update([
                'status' => 'failed',
                'failure_stage' => 'import',
                'completed_at' => date('Y-m-d H:i:s'),
                'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', $exception->getMessage());
        }
    }

    public function retry(int $id)
    {
        $model = new PlacementImportJobModel();
        $job = $model->find($id);
        if ($job === null) {
            throw PageNotFoundException::forPageNotFound('Import log not found.');
        }
        if ($job['status'] !== 'failed') {
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', 'Only a failed job can be retried.');
        }
        $phase = ($job['failure_stage'] ?? 'preview') === 'import' ? 'import' : 'preview';
        $status = $phase === 'import' ? 'queued' : 'queued_preview';
        $retryData = [
            'status' => $status,
            'failure_stage' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'summary' => json_encode(['message' => ucfirst($phase) . ' retry queued.']),
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'error_rows' => 0,
        ];
        if ($phase === 'preview') {
            $retryData += ['valid_rows' => 0, 'warning_rows' => 0, 'duplicate_rows' => 0, 'rejected_rows' => 0, 'preview_rows' => null, 'previewed_at' => null];
        }
        $model->update($id, $retryData);
        try {
            (new PlacementImportDispatcher())->dispatch($id, $phase);
            (new AdminAuditService())->record('placements.import_retried', null, ['import_job_id' => $id, 'phase' => $phase]);
            return redirect()->to(url_to('placements.logs.show', $id))->with('success', ucfirst($phase) . ' retry queued.');
        } catch (Throwable $exception) {
            $model->update($id, ['status' => 'failed', 'failure_stage' => $phase, 'summary' => json_encode(['message' => $exception->getMessage()])]);
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', $exception->getMessage());
        }
    }

    public function cancel(int $id)
    {
        $allowed = ['queued_preview', 'preview_ready', 'queued', 'failed'];
        $db = db_connect();
        $db->table('placement_import_jobs')->where('id', $id)->whereIn('status', $allowed)->update([
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'summary' => json_encode(['message' => 'Cancelled by an administrator.']),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($db->affectedRows() !== 1) {
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', 'Only queued, preview-ready, or failed jobs can be cancelled.');
        }
        (new AdminAuditService())->record('placements.import_cancelled', null, ['import_job_id' => $id]);
        return redirect()->to(url_to('placements.logs.show', $id))->with('success', 'Import job cancelled.');
    }

    public function workflow(int $id, string $action)
    {
        try {
            $status = (new PlacementDatasetWorkflowService())->transition($id, $action, (int) auth()->id());
            (new AdminAuditService())->record('placement.dataset_' . $status, null, ['import_job_id' => $id]);
            return redirect()->to(url_to('placements.logs.show', $id))->with('success', 'Dataset marked ' . ucfirst($status) . '.');
        } catch (Throwable $exception) {
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', $exception->getMessage());
        }
    }

    public function rejected(int $id): ResponseInterface
    {
        $job = (new PlacementImportJobModel())->find($id);
        if ($job === null) {
            throw PageNotFoundException::forPageNotFound('Import log not found.');
        }
        $rows = db_connect()->table('placement_import_errors')->where('import_job_id', $id)->orderBy('row_number')->get()->getResultArray();
        if ($rows === []) {
            return redirect()->to(url_to('placements.logs.show', $id))->with('error', 'This import has no rejected rows.');
        }

        $grouped = [];
        foreach ($rows as $error) {
            $key = (int) $error['row_number'];
            $grouped[$key] ??= ['payload' => json_decode((string) $error['row_payload'], true) ?: [], 'reasons' => []];
            $grouped[$key]['reasons'][] = ($error['field_name'] ? $error['field_name'] . ': ' : '') . $error['error_message'];
        }
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $headers = array_map(static fn (array $aliases): string => $aliases[0], PlacementSchema::HEADERS);
        $sheet->fromArray(array_merge(['Excel Row', 'Error Reasons'], $headers), null, 'A1');
        $line = 2;
        foreach ($grouped as $rowNumber => $item) {
            $sheet->fromArray(array_merge([$rowNumber, implode(' | ', array_unique($item['reasons']))], array_values($item['payload'])), null, 'A' . $line++);
        }
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getColumnDimension('B')->setWidth(60);
        $temporary = tempnam(WRITEPATH . 'cache', 'rejected-');
        (new Xlsx($book))->save($temporary);
        $book->disconnectWorksheets();
        $contents = (string) file_get_contents($temporary);
        @unlink($temporary);
        return $this->response->download('placement-import-' . $id . '-rejected.xlsx', $contents, true);
    }

    public function progress(string $token): ResponseInterface
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false]);
        }

        $job = (new PlacementImportJobModel())
            ->select('id, status, total_rows, valid_rows, warning_rows, duplicate_rows, rejected_rows, inserted_rows, updated_rows, skipped_rows, error_rows, summary, started_at, updated_at')
            ->where('progress_token', $token)
            ->first();
        if ($job === null) {
            return $this->response
                ->setHeader('Cache-Control', 'no-store')
                ->setJSON(['success' => true, 'found' => false]);
        }

        $recoveryQueued = $this->recoverStalledJob($job);

        $processedRows = in_array($job['status'], ['queued_preview', 'previewing', 'preview_ready'], true)
            ? ((int) $job['valid_rows'] + (int) $job['warning_rows'] + (int) $job['duplicate_rows'] + (int) $job['rejected_rows'])
            : ((int) $job['inserted_rows']
            + (int) $job['updated_rows']
            + (int) $job['skipped_rows']
            + (int) $job['error_rows']);
        $summary = json_decode((string) ($job['summary'] ?? ''), true);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON([
                'success' => true,
                'found' => true,
                'jobId' => (int) $job['id'],
                'status' => $job['status'],
                'totalRows' => (int) $job['total_rows'],
                'processedRows' => $processedRows,
                'insertedRows' => (int) $job['inserted_rows'],
                'updatedRows' => (int) $job['updated_rows'],
                'skippedRows' => (int) $job['skipped_rows'],
                'errorRows' => (int) $job['error_rows'],
                'validRows' => (int) $job['valid_rows'],
                'warningRows' => (int) $job['warning_rows'],
                'duplicateRows' => (int) $job['duplicate_rows'],
                'rejectedRows' => (int) $job['rejected_rows'],
                'message' => $recoveryQueued
                    ? 'The queued worker was restarted automatically.'
                    : (is_array($summary) ? ($summary['message'] ?? null) : null),
            ]);
    }

    private function recoverStalledJob(array $job): bool
    {
        $status = (string) ($job['status'] ?? '');
        $phase = match ($status) {
            'queued_preview', 'previewing' => 'preview',
            'queued' => 'import',
            default => null,
        };
        $stalledWhileRunning = $status === 'previewing';
        if ($phase === null || (! $stalledWhileRunning && ! empty($job['started_at']))) {
            return false;
        }

        $updatedAt = strtotime((string) ($job['updated_at'] ?? ''));
        $staleSeconds = $stalledWhileRunning ? self::STALLED_PREVIEW_SECONDS : self::STALLED_QUEUE_SECONDS;
        if ($updatedAt !== false && $updatedAt > time() - $staleSeconds) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $db = db_connect();
        $builder = $db->table('placement_import_jobs')
            ->where('id', (int) $job['id'])
            ->where('status', $status);
        if (! $stalledWhileRunning) {
            $builder->where('started_at IS NULL', null, false);
        }
        if (! empty($job['updated_at'])) {
            $builder->where('updated_at', $job['updated_at']);
        }
        $recoveryData = ['updated_at' => $now];
        if ($stalledWhileRunning) {
            $recoveryData += [
                'status' => 'queued_preview',
                'started_at' => null,
                'valid_rows' => 0,
                'warning_rows' => 0,
                'duplicate_rows' => 0,
                'rejected_rows' => 0,
                'error_rows' => 0,
                'preview_rows' => null,
            ];
        }
        $builder->update($recoveryData);
        if ($db->affectedRows() !== 1) {
            return false;
        }

        try {
            (new PlacementImportDispatcher())->dispatch((int) $job['id'], $phase);

            return true;
        } catch (Throwable $exception) {
            (new PlacementImportJobModel())->update((int) $job['id'], [
                'status' => 'failed',
                'failure_stage' => $phase,
                'completed_at' => $now,
                'summary' => json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);
            log_message('error', 'Could not recover stalled placement import job #{jobId}: {message}', [
                'jobId' => (int) $job['id'],
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function importResponse(bool $success, string $message, int $status, array $extra = []): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(array_merge([
                'success' => $success,
                'message' => $message,
                'csrf' => [
                    'name' => csrf_token(),
                    'hash' => csrf_hash(),
                ],
                'nextProgressToken' => bin2hex(random_bytes(16)),
            ], $extra));
    }

    private function uploadFailure(?UploadedFile $file)
    {
        $code = $file?->getError() ?? UPLOAD_ERR_NO_FILE;
        $serverLimit = trim((string) ini_get('upload_max_filesize')) ?: 'the configured PHP limit';
        [$status, $message] = match ($code) {
            UPLOAD_ERR_INI_SIZE => [413, 'The workbook exceeds the live server upload limit (' . $serverLimit . '). Increase PHP upload_max_filesize and post_max_size, then try again.'],
            UPLOAD_ERR_FORM_SIZE => [413, 'The workbook exceeds the permitted form upload size.'],
            UPLOAD_ERR_PARTIAL => [422, 'The workbook was only partially uploaded. Check the connection and try again.'],
            UPLOAD_ERR_NO_FILE => [422, 'Choose an Excel workbook before starting the import.'],
            UPLOAD_ERR_NO_TMP_DIR => [500, 'The live server has no temporary upload directory. Configure PHP upload_tmp_dir.'],
            UPLOAD_ERR_CANT_WRITE => [500, 'The live server could not write the temporary upload. Check disk space and upload_tmp_dir permissions.'],
            UPLOAD_ERR_EXTENSION => [422, 'A PHP extension or server security rule blocked the workbook upload.'],
            default => [422, $file?->getErrorString() ?: 'The workbook upload failed for an unknown reason.'],
        };

        log_message('warning', 'Placement workbook upload rejected with PHP code {code}. upload_max_filesize={uploadMax}; post_max_size={postMax}; upload_tmp_dir={tmpDir}', [
            'code' => $code,
            'uploadMax' => ini_get('upload_max_filesize'),
            'postMax' => ini_get('post_max_size'),
            'tmpDir' => ini_get('upload_tmp_dir') ?: '(system default)',
        ]);

        if ($this->request->isAJAX()) {
            return $this->importResponse(false, $message, $status, [
                'errors' => [$message],
                'uploadErrorCode' => $code,
                'serverUploadLimit' => $serverLimit,
            ]);
        }

        return redirect()->back()->withInput()->with('errors', [$message]);
    }

    private function actorNames(array $job): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', [
            $job['imported_by'] ?? null,
            $job['reviewed_by'] ?? null,
            $job['approved_by'] ?? null,
            $job['published_by'] ?? null,
        ]))));
        if ($ids === []) {
            return [];
        }
        $rows = db_connect()->table('users')->select('id, username')->whereIn('id', $ids)->get()->getResultArray();
        return array_column($rows, 'username', 'id');
    }
}


