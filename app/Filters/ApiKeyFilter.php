<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $configuredKey = trim((string) env('dhe.apiKey', ''));
        if ($configuredKey === '') {
            return null;
        }

        $providedKey = trim($request->getHeaderLine('X-API-Key'));
        if ($providedKey !== '' && hash_equals($configuredKey, $providedKey)) {
            return null;
        }

        return Services::response()
            ->setStatusCode(401)
            ->setJSON([
                'success' => false,
                'message' => 'A valid X-API-Key header is required.',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
