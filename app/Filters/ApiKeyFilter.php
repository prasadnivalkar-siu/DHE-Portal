<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class ApiKeyFilter implements FilterInterface
{
    private ?array $activityVendor = null;
    private float $activityStartedAt;
    private string $activityCategory = '';
    private string $activityAction = '';
    private string $activityMethod = '';
    private string $activityIp = '';

    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $this->activityVendor = null;
        $this->activityStartedAt = microtime(true);
        service('vendorApiContext')->set(null);
        $providedKey = trim($request->getHeaderLine('X-API-Key'));
        try {
            $vendor = service('vendorApiKeys')->authenticate($providedKey);
        } catch (\Throwable $exception) {
            log_message('error', 'Vendor API authentication unavailable: {message}', ['message' => $exception->getMessage()]);

            return $this->error(503, 'API authentication is temporarily unavailable. Please retry.');
        }
        if ($vendor === null) {
            return $this->error(401, 'A valid, unexpired API key for an active vendor is required in X-API-Key.');
        }

        $segments = explode('/', trim($request->getUri()->getPath(), '/'));
        $apiIndex = array_search('v1', $segments, true);
        $category = $apiIndex === false ? '' : ($segments[$apiIndex + 1] ?? '');
        $this->activityVendor = $vendor;
        $this->activityCategory = $category;
        $this->activityAction = match ($segments[$apiIndex + 2] ?? '') {
            'import' => ($segments[$apiIndex + 3] ?? '') === 'status' ? 'import_status' : 'import',
            'acknowledge' => 'acknowledge', default => 'retrieve',
        };
        $this->activityMethod = $request->getMethod();
        $this->activityIp = $request->getIPAddress();

        if (($arguments[0] ?? null) === 'ingest' && strlen((string) $request->getBody()) > 8 * 1024 * 1024) {
            return $this->error(413, 'Import requests may not exceed 8 MB. Send smaller batches.');
        }
        try {
            $payload = $request->getJSON(true);
        } catch (\Throwable) {
            return $this->error(400, 'The request body must contain a valid JSON object.');
        }
        if (! is_array($payload)) {
            return $this->error(400, 'The request body must contain a valid JSON object.');
        }
        // Check both sources, even when the payload would normally take precedence.
        foreach ([$payload['Vendor_Code'] ?? '', $request->getHeaderLine('X-Vendor-Code')] as $claimedCode) {
            if (! is_scalar($claimedCode) && $claimedCode !== null) {
                return $this->error(403, 'Vendor_Code must match the vendor assigned to this API key.');
            }
            $claimedCode = trim((string) $claimedCode);
            if ($claimedCode !== '' && $claimedCode !== $vendor['vendor_code']) {
                return $this->error(403, 'Vendor_Code must match the vendor assigned to this API key.');
            }
        }

        $ingesting = ($arguments[0] ?? null) === 'ingest';
        $allowed = json_decode((string) ($vendor[$ingesting ? 'ingestion_categories' : 'allowed_categories'] ?? '[]'), true);
        if (! is_array($allowed) || ! in_array($category, $allowed, true)) {
            return $this->error(403, $ingesting ? 'This vendor is not authorized to submit this data category.' : 'This vendor is not authorized for this data category.');
        }

        if (! $ingesting && array_key_exists('University_AISHE_Code', $payload)) {
            if (! is_string($payload['University_AISHE_Code'])) {
                return $this->error(422, 'University_AISHE_Code must be a string.');
            }
            if (trim($payload['University_AISHE_Code']) !== '' && ! (new \App\Services\VendorInstitutionScope($vendor))->allowsUniversity($payload['University_AISHE_Code'])) {
                return $this->error(403, 'This vendor is not authorized for the requested university.');
            }
        }

        try {
            service('vendorApiKeys')->recordUsage((int) $vendor['id'], $vendor['_authenticated_key_hash'] ?? null);
        } catch (\Throwable $exception) {
            log_message('error', 'Could not record vendor API usage: {message}', ['message' => $exception->getMessage()]);
            return $this->error(503, 'API authentication is temporarily unavailable. Please retry.');
        }
        service('vendorApiContext')->set($vendor);

        return null;
    }

    private function error(int $status, string $message): ResponseInterface
    {
        $response = Services::response()
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'message' => $message,
            ]);
        // CI skips after filters when a before filter rejects a request.
        $this->recordActivity($response);
        return $response;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $this->recordActivity($response);
    }

    private function recordActivity(ResponseInterface $response): void
    {
        if ($this->activityVendor === null) {
            return;
        }
        $vendor = $this->activityVendor;
        $this->activityVendor = null;
        $response->setHeader('X-API-Key-Status', $vendor['_authenticated_key_slot'] ?? 'current');
        if (! empty($vendor['_authenticated_key_expires_at'])) {
            $response->setHeader('X-API-Key-Expires-At', (new \DateTimeImmutable($vendor['_authenticated_key_expires_at']))->format(DATE_ATOM));
        }
        service('vendorActivityRecorder')->record($vendor, $this->activityCategory, $this->activityAction, $this->activityMethod, $this->activityIp, $this->activityStartedAt, $response);
    }
}
