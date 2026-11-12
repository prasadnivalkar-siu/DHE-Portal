<?php

namespace App\Database\Migrations;

use App\Services\VendorApiKeyService;
use CodeIgniter\Database\Migration;

final class AddEncryptedVendorApiKeys extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('api_key_encrypted', 'api_vendors')) {
            $this->forge->addColumn('api_vendors', [
                'api_key_encrypted' => ['type' => 'TEXT', 'null' => true],
            ]);
        }
        $legacyKey = trim((string) env('dhe.apiKey', ''));
        if ($legacyKey !== '' && config('Encryption')->key !== '') {
            $this->db->table('api_vendors')->where('api_key_hash', hash('sha256', $legacyKey))
                ->where('api_key_encrypted IS NULL', null, false)
                ->update(['api_key_encrypted' => (new VendorApiKeyService())->encryptKey($legacyKey)]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('api_vendors', 'api_key_encrypted');
    }
}
