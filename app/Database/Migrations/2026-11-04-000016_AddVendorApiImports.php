<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddVendorApiImports extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('api_vendors', ['ingestion_categories' => ['type' => 'TEXT', 'null' => true]]);
        foreach (['admission', 'examination', 'result', 'intake', 'expense', 'placement'] as $prefix) {
            $this->forge->addColumn($prefix . '_import_jobs', [
                'source_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'excel'],
                'source_vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ]);
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_id' => ['type' => 'INT', 'unsigned' => true],
            'vendor_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'category' => ['type' => 'VARCHAR', 'constraint' => 20],
            'import_job_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'request_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'response_code' => ['type' => 'SMALLINT', 'unsigned' => true],
            'response_payload' => ['type' => 'LONGTEXT'],
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_id', 'category', 'idempotency_key'], 'uq_vendor_import_request');
        $this->forge->addUniqueKey(['category', 'import_job_id'], 'uq_vendor_import_job');
        $this->forge->createTable('vendor_api_imports', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'row_number' => ['type' => 'INT', 'unsigned' => true],
            'outcome' => ['type' => 'VARCHAR', 'constraint' => 20],
            'record_key' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'row_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'base_row_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'normalized_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'submitted_payload' => ['type' => 'LONGTEXT'],
            'errors' => ['type' => 'LONGTEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['batch_id', 'row_number']);
        $this->forge->addForeignKey('batch_id', 'vendor_api_imports', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('vendor_api_import_rows', true);

        $this->forge->addField([
            'vendor_id' => ['type' => 'INT', 'unsigned' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 20],
            'window_started_at' => ['type' => 'DATETIME'],
            'request_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey(['vendor_id', 'category'], true);
        $this->forge->createTable('vendor_api_import_rate_limits', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('vendor_api_import_rate_limits', true);
        $this->forge->dropTable('vendor_api_import_rows', true);
        $this->forge->dropTable('vendor_api_imports', true);
        foreach (['admission', 'examination', 'result', 'intake', 'expense', 'placement'] as $prefix) {
            $this->forge->dropColumn($prefix . '_import_jobs', ['source_type', 'source_vendor_code']);
        }
        $this->forge->dropColumn('api_vendors', 'ingestion_categories');
    }
}
