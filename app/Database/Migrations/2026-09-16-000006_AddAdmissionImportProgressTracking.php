<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAdmissionImportProgressTracking extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('admission_import_jobs', [
            'progress_token' => [
                'type' => 'CHAR',
                'constraint' => 32,
                'null' => true,
                'after' => 'id',
            ],
        ]);
        $this->db->query('CREATE UNIQUE INDEX uq_admission_import_progress_token ON admission_import_jobs (progress_token)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX uq_admission_import_progress_token ON admission_import_jobs');
        $this->forge->dropColumn('admission_import_jobs', 'progress_token');
    }
}
