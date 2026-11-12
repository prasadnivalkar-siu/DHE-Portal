<?php

namespace Tests\Unit;

use App\Services\VendorActivityRecorder;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class VendorActivityRecorderTest extends CIUnitTestCase
{
    public function testOnlyWhitelistedMetadataIsStored(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $builder = $this->createMock(BaseBuilder::class);
        $db->method('table')->with('vendor_api_activity')->willReturn($builder);
        $builder->expects(self::once())->method('insert')->with(self::callback(static function (array $row): bool {
            self::assertSame(['vendor_id', 'vendor_code', 'category', 'action', 'method', 'response_code', 'is_replay', 'duration_ms', 'response_bytes', 'request_ip', 'requested_at'], array_keys($row));
            self::assertSame(1, $row['is_replay']);
            self::assertSame(200, $row['response_code']);
            self::assertSame('POST', $row['method']);
            self::assertGreaterThanOrEqual(0, $row['duration_ms']);
            self::assertStringNotContainsString('private-student-record', json_encode($row));
            self::assertStringNotContainsString('private-key', json_encode($row));
            return true;
        }))->willReturn(true);
        $response = service('response')->setStatusCode(200)->setHeader('X-Idempotency-Replayed', 'true')->setJSON(['data' => ['private-student-record']]);
        (new VendorActivityRecorder($db))->record(['id' => 7, 'vendor_code' => 'vendor-a', 'api_key_encrypted' => 'private-key'], 'admissions', 'retrieve', 'post', '127.0.0.1', microtime(true), $response);
    }

    public function testStorageFailureDoesNotChangeOrPreventTheApiResponse(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willThrowException(new \RuntimeException('Storage unavailable'));
        $response = service('response')->setStatusCode(201)->setJSON(['success' => true, 'batchId' => 42]);
        $body = $response->getBody();
        (new VendorActivityRecorder($db))->record(['id' => 7, 'vendor_code' => 'vendor-a'], 'admissions', 'import', 'POST', '127.0.0.1', microtime(true), $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame($body, $response->getBody());
    }
}
