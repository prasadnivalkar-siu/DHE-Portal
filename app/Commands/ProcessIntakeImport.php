<?php

namespace App\Commands;

use App\Services\IntakeImportService;
use App\Services\IntakeImportPreviewService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

final class ProcessIntakeImport extends BaseCommand
{
    protected $group = 'DHE';
    protected $name = 'dhe:process-intake-import';
    protected $description = 'Processes one queued Intake Data import job.';
    protected $usage = 'dhe:process-intake-import <job-id> [preview|import]';
    protected $arguments = ['job-id' => 'Queued intake import job ID.', 'phase' => 'Worker phase: preview or import.'];

    public function run(array $params): int
    {
        $jobId = filter_var($params[0] ?? null, FILTER_VALIDATE_INT);
        if ($jobId === false || $jobId < 1) {
            CLI::error('Provide a valid queued import job ID.');

            return EXIT_ERROR;
        }

        try {
            $phase = strtolower((string) ($params[1] ?? 'import'));
            if ($phase === 'preview') {
                (new IntakeImportPreviewService())->processQueued($jobId);
            } elseif ($phase === 'import') {
                (new IntakeImportService())->processQueued($jobId);
            } else {
                throw new \RuntimeException('Phase must be preview or import.');
            }
            CLI::write(ucfirst($phase) . ' job #' . $jobId . ' completed.', 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $exception) {
            CLI::error('Import job #' . $jobId . ' failed: ' . $exception->getMessage());

            return EXIT_ERROR;
        }
    }
}


