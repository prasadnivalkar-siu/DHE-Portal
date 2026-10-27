<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Runs (or reverses) exactly one migration file, regardless of what else is
 * pending — wraps MigrationRunner::force(), which core CodeIgniter doesn't
 * expose as a spark command on its own.
 */
class MigrateOne extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'migrate:one';
    protected $description = 'Runs (or reverses) a single migration file by name, ignoring any other pending migrations.';
    protected $usage       = 'migrate:one <filename>';
    protected $arguments   = [
        'filename' => 'The migration filename, with or without .php (e.g. 2026-09-18-000001_CreateEmployeeTables)',
    ];

    public function run(array $params)
    {
        $filename = array_shift($params);

        if (empty($filename)) {
            CLI::error('You must supply a migration filename. Example:');
            CLI::write('  php spark migrate:one 2026-09-18-000001_CreateEmployeeTables');

            return;
        }

        if (! str_ends_with($filename, '.php')) {
            $filename .= '.php';
        }

        $path = APPPATH . 'Database/Migrations/' . $filename;

        if (! is_file($path)) {
            CLI::error('Migration file not found: ' . $path);

            return;
        }

        $runner = Services::migrations();
        $runner->clearCliMessages();

        CLI::write('Running single migration: ' . $filename, 'yellow');

        $ok = $runner->force($path, 'App');

        foreach ($runner->getCliMessages() as $message) {
            CLI::write($message);
        }

        if ($ok) {
            CLI::write('Done.', 'green');
        } else {
            CLI::error('Migration failed. See messages above.');
        }
    }
}
