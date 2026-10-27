<?php

namespace App\Services;

use RuntimeException;

final class AdmissionApiIdempotencyService
{
    public function requestHash(array $payload): string
    {
        ksort($payload);
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{code: int, payload: array}|null */
    public function claim(string $vendorCode, string $key, string $requestHash): ?array
    {
        $db = db_connect();
        $row = $db->table('admission_api_idempotency')->where('vendor_code', $vendorCode)->where('idempotency_key', $key)->get()->getRowArray();
        if ($row === null) {
            try {
                $db->table('admission_api_idempotency')->insert([
                    'vendor_code' => $vendorCode,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'response_code' => null,
                    'response_payload' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                ]);
                return null;
            } catch (\Throwable) {
                $row = $db->table('admission_api_idempotency')->where('vendor_code', $vendorCode)->where('idempotency_key', $key)->get()->getRowArray();
                if ($row === null) {
                    throw new RuntimeException('The idempotency reservation could not be created.');
                }
            }
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            $db->table('admission_api_idempotency')->where('id', $row['id'])->delete();
            return $this->claim($vendorCode, $key, $requestHash);
        }
        if (! hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new RuntimeException('This Idempotency-Key was already used with a different request payload.');
        }
        if ($row['response_payload'] === null) {
            throw new RuntimeException('A request with this Idempotency-Key is still being processed.');
        }
        $json = @gzuncompress((string) $row['response_payload']);
        $payload = json_decode($json === false ? (string) $row['response_payload'] : $json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('The stored idempotent response is unavailable.');
        }
        $payload['idempotencyReplay'] = true;
        return ['code' => (int) $row['response_code'], 'payload' => $payload];
    }

    public function store(string $vendorCode, string $key, string $requestHash, int $code, array $payload): void
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db = db_connect();
        $db->table('admission_api_idempotency')
            ->where('vendor_code', $vendorCode)
            ->where('idempotency_key', $key)
            ->where('request_hash', $requestHash)
            ->update([
            'response_code' => $code,
            'response_payload' => gzcompress($json, 6),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
        if ($db->affectedRows() !== 1) {
            throw new RuntimeException('The idempotency reservation is missing or has changed.');
        }
    }

    public function release(string $vendorCode, string $key, string $requestHash): void
    {
        db_connect()->table('admission_api_idempotency')
            ->where('vendor_code', $vendorCode)
            ->where('idempotency_key', $key)
            ->where('request_hash', $requestHash)
            ->where('response_payload IS NULL', null, false)
            ->delete();
    }
}
