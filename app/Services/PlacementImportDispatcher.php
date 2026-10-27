<?php

namespace App\Services;

use RuntimeException;
use Throwable;

final class PlacementImportDispatcher
{
    public function dispatch(int $jobId, string $phase = 'import'): void
    {
        if ($jobId < 1) {
            throw new RuntimeException('A valid import job is required.');
        }
        if (! in_array($phase, ['preview', 'import'], true)) {
            throw new RuntimeException('The import worker phase is invalid.');
        }

        $php = $this->phpBinary();
        $spark = ROOTPATH . 'spark';
        $log = WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . 'placement-import-worker-' . $jobId . '.log';

        if (PHP_OS_FAMILY === 'Windows' && $this->functionAvailable('popen')) {
            $command = 'start "" /B '
                . $this->windowsQuote($php) . ' '
                . $this->windowsQuote($spark) . ' dhe:process-placement-import '
                . $jobId . ' ' . $phase . ' > ' . $this->windowsQuote($log) . ' 2>&1';
            $handle = @\popen($command, 'r');
            if (is_resource($handle)) {
                \pclose($handle);

                return;
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $command = escapeshellarg($php) . ' '
                . escapeshellarg($spark) . ' dhe:process-placement-import '
                . $jobId . ' ' . escapeshellarg($phase) . ' >> ' . escapeshellarg($log) . ' 2>&1 &';

            if ($this->functionAvailable('exec')) {
                $output = [];
                $exitCode = 1;
                @\exec($command, $output, $exitCode);
                if ($exitCode === 0) {
                    return;
                }
            }

            // Do not use popen() as a Unix fallback. It can successfully open
            // the shell even when the detached PHP command immediately fails,
            // leaving the database job permanently queued.
        }

        $this->deferInCurrentProcess($jobId, $phase);
    }

    private function deferInCurrentProcess(int $jobId, string $phase): void
    {
        log_message('warning', 'Shell execution is unavailable; placement import job #{jobId} will run after the HTTP response.', [
            'jobId' => $jobId,
        ]);

        register_shutdown_function(static function () use ($jobId, $phase): void {
            if (function_exists('set_time_limit')) {
                @\set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                \ignore_user_abort(true);
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                @\session_write_close();
            }

            // Release the browser request before parsing the workbook. PHP-FPM
            // and LiteSpeed expose different functions for this operation.
            if (function_exists('fastcgi_finish_request')) {
                @\fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                @\litespeed_finish_request();
            } else {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                @flush();
            }

            try {
                if ($phase === 'preview') {
                    (new PlacementImportPreviewService())->processQueued($jobId);
                } else {
                    (new PlacementImportService())->processQueued($jobId);
                }
            } catch (Throwable $exception) {
                log_message('error', 'Placement import job #{jobId} failed: {message}', [
                    'jobId' => $jobId,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function functionAvailable(string $name): bool
    {
        if (! function_exists($name)) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array($name, $disabled, true);
    }

    private function phpBinary(): string
    {
        $configured = trim((string) env('dhe.importPhpBinary', ''));
        if ($configured !== '') {
            return $configured;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $cli = rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
            if (is_file($cli) && is_executable($cli)) {
                return $cli;
            }
        }

        return PHP_BINARY;
    }

    private function windowsQuote(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}


