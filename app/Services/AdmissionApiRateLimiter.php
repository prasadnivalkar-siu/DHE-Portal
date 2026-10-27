<?php

namespace App\Services;

use RuntimeException;
use Throwable;

final class AdmissionApiRateLimiter
{
    /** @return array{allowed: bool, limit: int, remaining: int, retryAfter: int} */
    public function consume(string $vendorCode): array
    {
        $limit = max(1, (int) env('dhe.apiRateLimitPerMinute', 60));
        $db = db_connect();
        $now = new \DateTimeImmutable();
        $window = $now->format('Y-m-d H:i:00');
        $db->transBegin();
        try {
            $row = $db->query('SELECT * FROM admission_api_rate_limits WHERE vendor_code = ? FOR UPDATE', [$vendorCode])->getRowArray();
            if ($row === null) {
                $count = 1;
                $db->table('admission_api_rate_limits')->insert(['vendor_code' => $vendorCode, 'window_started_at' => $window, 'request_count' => $count, 'updated_at' => $now->format('Y-m-d H:i:s')]);
            } elseif ((string) $row['window_started_at'] !== $window) {
                $count = 1;
                $db->table('admission_api_rate_limits')->where('vendor_code', $vendorCode)->update(['window_started_at' => $window, 'request_count' => $count, 'updated_at' => $now->format('Y-m-d H:i:s')]);
            } else {
                $count = (int) $row['request_count'] + 1;
                if ($count <= $limit) {
                    $db->table('admission_api_rate_limits')->where('vendor_code', $vendorCode)->update(['request_count' => $count, 'updated_at' => $now->format('Y-m-d H:i:s')]);
                }
            }
            if (! $db->transStatus()) {
                throw new RuntimeException('Rate-limit counter failed.');
            }
            $db->transCommit();
            $retryAfter = 60 - (int) $now->format('s');
            return ['allowed' => $count <= $limit, 'limit' => $limit, 'remaining' => max(0, $limit - $count), 'retryAfter' => $retryAfter];
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
}
