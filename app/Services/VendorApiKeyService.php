<?php

namespace App\Services;

use App\Models\ApiVendorModel;
use App\Libraries\VendorKeyPolicy;
use CodeIgniter\Encryption\EncrypterInterface;

class VendorApiKeyService
{
    public const CATEGORIES = [
        'admissions' => 'Admission',
        'examinations' => 'Examination',
        'results' => 'Result',
        'intake' => 'Intake',
        'expenses' => 'Expenses',
        'placements' => 'Placement',
    ];

    public function __construct(private ?ApiVendorModel $vendors = null, private ?EncrypterInterface $encrypter = null)
    {
    }

    /** Authentication uses the hash; the encrypted copy supports authorized administrator reveal. */
    public function generate(): array
    {
        $key = 'dhe_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [
            'key' => $key,
            'api_key_hash' => hash('sha256', $key),
            'api_key_prefix' => substr($key, 0, 12),
            'api_key_encrypted' => $this->encryptKey($key),
            'key_generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function encryptKey(#[\SensitiveParameter] string $key): string
    {
        return base64_encode(($this->encrypter ??= service('encrypter'))->encrypt($key));
    }

    public function reveal(array $vendor): ?string
    {
        $encrypted = $vendor['api_key_encrypted'] ?? null;
        if ($encrypted === null || $encrypted === '') {
            // Only recover a known configured key when it still matches this vendor's
            // current hash. A regenerated/revoked legacy key must never be resurrected.
            $legacyKey = trim((string) env('dhe.apiKey', ''));
            if ($legacyKey === '' || ! hash_equals($vendor['api_key_hash'], hash('sha256', $legacyKey))) {
                return null;
            }
            $encrypted = $this->encryptKey($legacyKey);
            if (! ($this->vendors ??= new ApiVendorModel())->storeEncryptedKey((int) $vendor['id'], $vendor['api_key_hash'], $encrypted)) {
                throw new \RuntimeException('The vendor key changed. Refresh and try again.');
            }
        }
        $ciphertext = base64_decode($encrypted, true);
        if ($ciphertext === false) {
            throw new \RuntimeException('The saved API key cannot be decrypted.');
        }
        $key = ($this->encrypter ??= service('encrypter'))->decrypt($ciphertext);
        if (! hash_equals($vendor['api_key_hash'], hash('sha256', $key))) {
            throw new \RuntimeException('The saved API key does not match the current credential.');
        }
        return $key;
    }

    public function authenticate(string $key): ?array
    {
        if ($key === '' || strlen($key) > 256) {
            return null;
        }
        $vendors = $this->vendors ??= new ApiVendorModel();
        $hash = hash('sha256', $key);
        $vendor = $vendors->findByKeyHash($hash);
        if ($vendor === null || (int) $vendor['is_active'] !== 1) {
            return null;
        }
        if (hash_equals($vendor['api_key_hash'], $hash) && VendorKeyPolicy::usable($vendor['api_key_expires_at'] ?? null)) {
            $vendor['_authenticated_key_slot'] = 'current';
            $vendor['_authenticated_key_expires_at'] = $vendor['api_key_expires_at'] ?? null;
        } elseif (is_string($vendor['previous_api_key_hash'] ?? null) && hash_equals($vendor['previous_api_key_hash'], $hash)
            && ! empty($vendor['previous_key_valid_until']) && VendorKeyPolicy::usable($vendor['previous_key_valid_until'])) {
            $vendor['_authenticated_key_slot'] = 'previous';
            $vendor['_authenticated_key_expires_at'] = $vendor['previous_key_valid_until'];
        } else {
            return null;
        }
        $vendor['_authenticated_key_hash'] = $hash;
        return $vendor;
    }

    public function recordUsage(int $id, ?string $keyHash = null): void
    {
        ($this->vendors ??= new ApiVendorModel())->recordUsage($id, $keyHash);
    }
}
