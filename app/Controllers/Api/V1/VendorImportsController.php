<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\VendorImportService;
use DomainException;
use Throwable;

final class VendorImportsController extends BaseController
{
    public function store(string $category)
    {
        if (strlen((string) $this->request->getBody()) > 8 * 1024 * 1024) {
            return $this->error(413, 'Import requests may not exceed 8 MB. Send smaller batches.');
        }
        $payload = $this->request->getJSON(true);
        $key = $payload['Idempotency_Key'] ?? $this->request->getHeaderLine('Idempotency-Key');
        if (! is_string($key) || preg_match('/^[a-zA-Z0-9._:-]{8,100}$/', $key) !== 1) {
            return $this->error(422, 'An Idempotency-Key header or Idempotency_Key payload value of 8–100 characters is required.');
        }
        if (array_diff(array_keys($payload), ['Vendor_Code', 'Idempotency_Key', 'Records']) !== []) {
            return $this->error(422, 'Only Records, Vendor_Code, and Idempotency_Key are allowed at the top level. Workflow status is controlled by the portal.');
        }
        $records = $payload['Records'] ?? null;
        if (! is_array($records) || ! array_is_list($records) || count($records) < 1 || count($records) > 500) {
            return $this->error(422, 'Records must be an array containing between 1 and 500 record objects.');
        }
        return $this->execute($category, static fn (VendorImportService $service, $context, string $ip): array => $service->submit($category, $context->vendorId(), $context->vendorCode(), $key, $records, $ip));
    }

    public function status(string $category)
    {
        $payload = $this->request->getJSON(true);
        $id = filter_var($payload['Batch_ID'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return $this->error(422, 'Batch_ID must be a positive batch identifier.');
        }
        return $this->execute($category, static function (VendorImportService $service, $context) use ($category, $id): array {
            $status = $service->status($category, $id, $context->vendorId());
            return $status === null
                ? ['code' => 404, 'payload' => ['success' => false, 'message' => 'Submission not found for this vendor and category.']]
                : ['code' => 200, 'payload' => $status];
        });
    }

    private function execute(string $category, callable $callback)
    {
        try {
            $context = service('vendorApiContext');
            $service = new VendorImportService();
            $rate = $service->consumeRate($context->vendorId(), $category, $context->rateLimit());
            $this->response->setHeader('X-RateLimit-Limit', (string) $rate['limit'])->setHeader('X-RateLimit-Remaining', (string) $rate['remaining']);
            if (! $rate['allowed']) {
                $this->response->setHeader('Retry-After', (string) $rate['retryAfter']);
                return $this->error(429, 'Vendor import request limit exceeded. Retry after the indicated delay.');
            }
            $result = $callback($service, $context, $this->request->getIPAddress());
            if ($result['replayed'] ?? false) {
                $this->response->setHeader('X-Idempotency-Replayed', 'true');
            }
            return $this->response->setStatusCode($result['code'])->setHeader('Cache-Control', 'no-store')->setJSON($result['payload']);
        } catch (DomainException $exception) {
            return $this->error($exception->getCode(), $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Vendor import failed: {message}', ['message' => $exception->getMessage()]);
            return $this->error(500, 'The import request could not be completed. Retry with the same idempotency key.');
        }
    }

    private function error(int $code, string $message)
    {
        return $this->response->setStatusCode($code)->setHeader('Cache-Control', 'no-store')->setJSON(['success' => false, 'message' => $message]);
    }
}
