<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddProgramCodeToIntakes extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('program_code', 'intakes')) {
            $this->forge->addColumn('intakes', [
                'program_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'institution_type_name',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('program_code', 'intakes')) {
            $this->forge->dropColumn('intakes', 'program_code');
        }
    }
}
