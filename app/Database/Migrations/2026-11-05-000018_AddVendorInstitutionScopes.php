<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddVendorInstitutionScopes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('api_vendors', [
            'institution_access_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'all'],
            'institution_access_rules' => ['type' => 'TEXT', 'null' => true],
        ]);
        foreach (['admission', 'examination', 'result', 'intake', 'expense', 'placement'] as $prefix) {
            foreach (['api_logs', 'api_sync_states'] as $table) {
                $this->forge->addColumn($prefix . '_' . $table, ['institution_scope_key' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '']]);
            }
        }
    }

    public function down(): void
    {
        foreach (['admission', 'examination', 'result', 'intake', 'expense', 'placement'] as $prefix) {
            foreach (['api_logs', 'api_sync_states'] as $table) {
                $this->forge->dropColumn($prefix . '_' . $table, 'institution_scope_key');
            }
        }
        $this->forge->dropColumn('api_vendors', ['institution_access_mode', 'institution_access_rules']);
    }
}
