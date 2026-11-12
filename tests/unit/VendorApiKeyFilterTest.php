<?php

namespace Tests\Unit;

use App\Filters\ApiKeyFilter;
use App\Services\VendorApiKeyService;
use App\Services\VendorActivityRecorder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;

final class VendorApiKeyFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('vendorActivityRecorder', $this->createMock(VendorActivityRecorder::class));
    }

    private function request(string $path, string $body = '{}', array $headers = []): IncomingRequest
    {
        $request = new IncomingRequest(new App(), new URI('https://example.com/dhe/api/v1/' . $path), $body, new UserAgent());
        $request->setHeader('Content-Type', 'application/json');
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        return $request;
    }

    private function useVendor(?array $vendor): VendorApiKeyService
    {
        $service = $this->createMock(VendorApiKeyService::class);
        $service->method('authenticate')->willReturn($vendor);
        Services::injectMock('vendorApiKeys', $service);
        return $service;
    }

    private function vendor(array $categories = ['admissions']): array
    {
        return ['id' => 7, 'vendor_code' => 'vendor-a', 'rate_limit_per_minute' => 12, 'allowed_categories' => json_encode($categories)];
    }

    public static function endpointProvider(): iterable
    {
        foreach (array_keys(VendorApiKeyService::CATEGORIES) as $category) {
            yield $category => [$category];
            yield $category . ' acknowledgment' => [$category . '/acknowledge'];
        }
    }

    /** @dataProvider endpointProvider */
    public function testAllEndpointsUseKeyIdentityWhenVendorCodeIsOmitted(string $path): void
    {
        $service = $this->useVendor($this->vendor(array_keys(VendorApiKeyService::CATEGORIES)));
        $service->expects(self::once())->method('recordUsage')->with(7);
        self::assertNull((new ApiKeyFilter())->before($this->request($path, '{}', ['X-API-Key' => 'valid-key'])));
        self::assertSame('vendor-a', service('vendorApiContext')->vendorCode());
        self::assertSame(12, service('vendorApiContext')->rateLimit());
    }

    public static function mismatchedCodeProvider(): iterable
    {
        yield 'body' => ['{"Vendor_Code":"vendor-b"}', []];
        yield 'header' => ['{}', ['X-Vendor-Code' => 'vendor-b']];
        yield 'conflicting header hidden by correct body' => ['{"Vendor_Code":"vendor-a"}', ['X-Vendor-Code' => 'vendor-b']];
        yield 'invalid type' => ['{"Vendor_Code":[]}', []];
    }

    /** @dataProvider mismatchedCodeProvider */
    public function testVendorImpersonationIsRejected(string $body, array $headers): void
    {
        $service = $this->useVendor($this->vendor());
        $service->expects(self::never())->method('recordUsage');
        $response = (new ApiKeyFilter())->before($this->request('admissions', $body, $headers));
        self::assertSame(403, $response->getStatusCode());
    }

    public function testDisallowedCategoryAlsoBlocksAcknowledgment(): void
    {
        $this->useVendor($this->vendor());
        $response = (new ApiKeyFilter())->before($this->request('results/acknowledge'));
        self::assertSame(403, $response->getStatusCode());
    }

    public function testUnknownKeysAreRejectedAndClearAnyPreviousContext(): void
    {
        service('vendorApiContext')->set($this->vendor());
        $this->useVendor(null);
        $response = (new ApiKeyFilter())->before($this->request('admissions'));
        self::assertSame(401, $response->getStatusCode());
        $this->expectException(\LogicException::class);
        service('vendorApiContext')->vendorCode();
    }

    public function testAuthenticationStorageFailureDoesNotGrantAccess(): void
    {
        $service = $this->createMock(VendorApiKeyService::class);
        $service->method('authenticate')->willThrowException(new \RuntimeException('Storage unavailable'));
        Services::injectMock('vendorApiKeys', $service);
        $response = (new ApiKeyFilter())->before($this->request('admissions'));
        self::assertSame(503, $response->getStatusCode());
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->useVendor($this->vendor());
        $response = (new ApiKeyFilter())->before($this->request('admissions', '{'));
        self::assertSame(400, $response->getStatusCode());
    }

    public static function ingestionEndpoints(): iterable
    {
        foreach (array_keys(VendorApiKeyService::CATEGORIES) as $category) {
            yield $category => [$category . '/import'];
            yield $category . ' status' => [$category . '/import/status'];
        }
    }

    /** @dataProvider ingestionEndpoints */
    public function testIngestionUsesSeparateCategoryPermissions(string $path): void
    {
        $vendor = $this->vendor([]);
        $vendor['ingestion_categories'] = json_encode(array_keys(VendorApiKeyService::CATEGORIES));
        $this->useVendor($vendor);
        self::assertNull((new ApiKeyFilter())->before($this->request($path), ['ingest']));
    }

    public function testConsumerCannotSubmitAndProducerCannotConsume(): void
    {
        $this->useVendor($this->vendor());
        self::assertSame(403, (new ApiKeyFilter())->before($this->request('admissions/import'), ['ingest'])->getStatusCode());
        $vendor = $this->vendor([]);
        $vendor['ingestion_categories'] = '["admissions"]';
        $this->useVendor($vendor);
        self::assertSame(403, (new ApiKeyFilter())->before($this->request('admissions'))->getStatusCode());
    }

    public function testUniversityOutsideInstitutionScopeIsRejectedBeforeDelivery(): void
    {
        $vendor = $this->vendor();
        $vendor['institution_access_mode'] = 'restricted';
        $vendor['institution_access_rules'] = '[{"university_code":"U-1234","institution_code":"001"}]';
        $service = $this->useVendor($vendor);
        $service->expects(self::never())->method('recordUsage');
        self::assertSame(403, (new ApiKeyFilter())->before($this->request('admissions', '{"University_AISHE_Code":"U-9999"}'))->getStatusCode());
    }

    public function testRejectedRequestIsRecordedOnceEvenWhenAfterIsInvoked(): void
    {
        $this->useVendor($this->vendor(['admissions']));
        $recorder = $this->createMock(VendorActivityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::anything(), 'results', 'import_status', 'GET', self::anything(), self::anything(), self::callback(static fn ($response) => $response->getStatusCode() === 403));
        Services::injectMock('vendorActivityRecorder', $recorder);
        $request = $this->request('results/import/status');
        $filter = new ApiKeyFilter();
        $response = $filter->before($request, ['ingest']);
        $filter->after($request, $response);
    }

    public function testSuccessfulRequestIsRecordedAfterTheControllerResponse(): void
    {
        $this->useVendor($this->vendor());
        $recorder = $this->createMock(VendorActivityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::anything(), 'admissions', 'retrieve', self::anything(), self::anything(), self::anything(), self::callback(static fn ($response) => $response->getHeaderLine('X-Idempotency-Replayed') === 'true'));
        Services::injectMock('vendorActivityRecorder', $recorder);
        $filter = new ApiKeyFilter();
        $request = $this->request('admissions');
        self::assertNull($filter->before($request));
        $filter->after($request, service('response')->setHeader('X-Idempotency-Replayed', 'true'));
    }

    public function testUnknownKeyDoesNotAttributeActivityToAnUntrustedVendorCode(): void
    {
        $this->useVendor(null);
        $recorder = $this->createMock(VendorActivityRecorder::class);
        $recorder->expects(self::never())->method('record');
        Services::injectMock('vendorActivityRecorder', $recorder);
        (new ApiKeyFilter())->before($this->request('admissions', '{"Vendor_Code":"vendor-a"}'));
    }
}
