<?php

namespace App\Commands;

use App\Services\ExaminationImportService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Psr\Log\LoggerInterface;
use Throwable;

final class ImportExaminations extends BaseCommand
{
    protected $group = 'DHE';
    protected $name = 'dhe:import-examinations';
    protected $description = 'Imports an Examination Data Excel workbook and creates an audit log.';
    protected $usage = 'dhe:import-examinations <path-to-workbook>';
    protected $arguments = ['path-to-workbook' => 'Absolute or project-relative path to an .xlsx or .xls workbook.'];

    public function run(array $params): int
    {
        $source = $params[0] ?? null;
        if ($source === null || ! is_file($source) || ! is_readable($source)) {
            CLI::error('Provide a readable path to an .xlsx or .xls workbook.');

            return EXIT_ERROR;
        }

        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            CLI::error('Only .xlsx and .xls workbooks are supported.');

            return EXIT_ERROR;
        }

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'examinations' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            CLI::error('Could not create the CLI import directory.');

            return EXIT_ERROR;
        }

        $storedName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (! copy($source, $destination)) {
            CLI::error('Could not copy the workbook into portal storage.');

            return EXIT_ERROR;
        }
        $relativePath = ltrim(str_replace(WRITEPATH, '', $destination), '\\/');

        try {
            $jobId = (new ExaminationImportService())->import($destination, basename($source), $relativePath);
            CLI::write('Import completed. Log ID: ' . $jobId, 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $exception) {
            CLI::error('Import failed: ' . $exception->getMessage());

            return EXIT_ERROR;
        }
    }
}

