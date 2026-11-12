<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

/** Lightweight request metadata only: never credentials, request bodies, or student records. */
class VendorActivityRecorder
{
    public function __construct(private ?BaseConnection $db = null)
    {
    }

    public function record(array $vendor, string $category, string $action, string $method, string $ip, float $startedAt, ResponseInterface $response): void
    {
        try {
            $recorded = ($this->db ??= db_connect())->table('vendor_api_activity')->insert([
                'vendor_id' => (int) $vendor['id'], 'vendor_code' => $vendor['vendor_code'],
                'category' => $category, 'action' => $action, 'method' => strtoupper($method),
                'response_code' => $response->getStatusCode(),
                'is_replay' => strtolower($response->getHeaderLine('X-Idempotency-Replayed')) === 'true' ? 1 : 0,
                'duration_ms' => min(4294967295, max(0, (int) round((microtime(true) - $startedAt) * 1000))),
                'response_bytes' => strlen((string) $response->getBody()),
                'request_ip' => substr($ip, 0, 64), 'requested_at' => date('Y-m-d H:i:s', (int) $startedAt),
            ]);
            if (! $recorded) {
                throw new \RuntimeException('Activity insert failed.');
            }
        } catch (\Throwable) {
            // Monitoring must not change a delivery's outcome or expose response content in logs.
            log_message('error', 'Vendor activity could not be recorded. Check the activity database migration and availability.');
        }
    }
}
