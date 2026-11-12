<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ApiVendorModel;
use App\Models\AdminAuditLogModel;
use App\Services\AdminAuditService;
use App\Services\VendorApiKeyService;
use App\Services\VendorInstitutionScope;
use App\Libraries\VendorKeyPolicy;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

final class VendorsController extends BaseController
{
    public function index(): string
    {
        $vendors = new ApiVendorModel();

        return view('admin/vendors/index', [
            'title' => 'Vendor Management',
            'vendors' => $vendors->select('id, vendor_code, name, contact_email, is_active, allowed_categories, ingestion_categories, institution_access_mode, institution_access_rules, rate_limit_per_minute, api_key_prefix, key_generated_at, last_used_at, api_key_expires_at, current_key_last_used_at, previous_api_key_prefix, previous_key_valid_until, previous_key_last_used_at')->orderBy('id', 'DESC')->paginate(25, 'vendors'),
            'pager' => $vendors->pager,
            'categories' => VendorApiKeyService::CATEGORIES,
        ]);
    }

    public function create(): string
    {
        return view('admin/vendors/form', [
            'title' => 'Create Vendor',
            'vendor' => null,
            'categories' => VendorApiKeyService::CATEGORIES,
            'issuedKey' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->validatedInput(true);
        if ($data === null) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $credentials = service('vendorApiKeys')->generate();
            $id = $this->mutate(function () use ($data, $credentials): int {
                $vendors = new ApiVendorModel();
                $id = $vendors->insert($data + [
                    'api_key_hash' => $credentials['api_key_hash'],
                    'api_key_prefix' => $credentials['api_key_prefix'],
                    'api_key_encrypted' => $credentials['api_key_encrypted'],
                    'key_generated_at' => $credentials['key_generated_at'],
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                if ($id === false) {
                    throw new \RuntimeException('Vendor could not be saved.');
                }
                (new AdminAuditService())->record('vendor.created', null, ['vendor_id' => $id, 'vendor_code' => $data['vendor_code'], 'institution_access_mode' => $data['institution_access_mode'], 'institution_access_rules' => json_decode($data['institution_access_rules'], true), 'api_key_expires_at' => $data['api_key_expires_at']]);
                return (int) $id;
            });
            session()->setFlashdata('vendor_issued_key', ['id' => $id, 'key' => $credentials['key'], 'prefix' => $credentials['api_key_prefix']]);

            return redirect()->to(url_to('admin.vendors.edit', $id))->with('success', 'Vendor created. Copy the API key below or use Show API key when needed.');
        } catch (Throwable $exception) {
            log_message('error', 'Vendor creation failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'The vendor could not be created. Check the vendor code is unique and try again.');
        }
    }

    public function edit(int $id): string
    {
        $vendor = $this->findVendor($id);
        $issued = session()->getFlashdata('vendor_issued_key');
        session()->remove('vendor_issued_key');
        $this->response->setHeader('Cache-Control', 'no-store, private')->setHeader('Referrer-Policy', 'no-referrer');

        return view('admin/vendors/form', [
            'title' => 'Manage Vendor',
            'vendor' => $vendor,
            'categories' => VendorApiKeyService::CATEGORIES,
            'issuedKey' => is_array($issued) && (int) $issued['id'] === $id && (! isset($issued['prefix']) || hash_equals($vendor['api_key_prefix'], $issued['prefix'])) ? $issued['key'] : null,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $vendor = $this->findVendor($id);
        $data = $this->validatedInput(false, $vendor);
        if ($data === null) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->mutate(function () use ($id, $data, $vendor): void {
                if (! (new ApiVendorModel())->update($id, $data + ['updated_by' => auth()->id()])) {
                    throw new \RuntimeException('Vendor could not be updated.');
                }
                (new AdminAuditService())->record('vendor.updated', null, [
                    'vendor_id' => $id, 'vendor_code' => $vendor['vendor_code'],
                    'is_active' => $data['is_active'], 'allowed_categories' => json_decode($data['allowed_categories'], true),
                    'ingestion_categories' => json_decode($data['ingestion_categories'], true),
                    'rate_limit_per_minute' => $data['rate_limit_per_minute'],
                ]);
                $oldScope = new VendorInstitutionScope($vendor);
                $newScope = new VendorInstitutionScope($data);
                if ($oldScope->key() !== $newScope->key()) {
                    (new AdminAuditService())->record('vendor.institution_scope_changed', null, [
                        'vendor_id' => $id, 'vendor_code' => $vendor['vendor_code'],
                        'previous_scope_key' => $oldScope->key(), 'scope_key' => $newScope->key(),
                        'mode' => $data['institution_access_mode'], 'rules' => json_decode($data['institution_access_rules'], true),
                    ]);
                }
            });
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('success', 'Vendor updated. Access changes apply to subsequent API requests.');
        } catch (Throwable $exception) {
            log_message('error', 'Vendor update failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'The vendor could not be updated.');
        }
    }

    public function revealKey(int $id)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('Pragma', 'no-cache')->setHeader('Referrer-Policy', 'no-referrer');
        $vendor = $this->findVendor($id);
        try {
            $key = service('vendorApiKeys')->reveal($vendor);
            if ($key === null) {
                return $this->keyResponse(409, [
                    'success' => false,
                    'message' => 'This older key was stored only as a hash and cannot be recovered. Rotate the API key to enable viewing, then provide the new key to the vendor.',
                ]);
            }
            if ((new AdminAuditLogModel())->insert([
                'actor_user_id' => auth()->id(), 'action' => 'vendor.key_viewed',
                'metadata' => json_encode(['vendor_id' => $id, 'vendor_code' => $vendor['vendor_code']]),
                'request_ip' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
            ]) === false) {
                throw new \RuntimeException('The key view could not be audited.');
            }
            return $this->keyResponse(200, ['success' => true, 'apiKey' => $key]);
        } catch (Throwable) {
            log_message('error', 'Vendor API key reveal failed for vendor ID {id}. Check encryption configuration.', ['id' => $id]);
            return $this->keyResponse(500, ['success' => false, 'message' => 'The API key could not be displayed. Check the server encryption configuration.']);
        }
    }

    private function keyResponse(int $status, array $payload)
    {
        return $this->response->setStatusCode($status)->setJSON($payload + [
            'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }

    public function regenerateKey(int $id): RedirectResponse
    {
        $this->findVendor($id);
        if ($this->request->getPost('confirm_regenerate') !== '1') {
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', 'Confirm the key rotation and the selected overlap period.');
        }
        try {
            $overlap = VendorKeyPolicy::overlapMinutes($this->request->getPost('overlap_minutes') ?? 0);
            $expiry = VendorKeyPolicy::expiration($this->request->getPost('new_key_expires_at') ?? (new \DateTimeImmutable('+' . VendorKeyPolicy::DEFAULT_LIFETIME_DAYS . ' days'))->format('Y-m-d\TH:i'));
            $credentials = service('vendorApiKeys')->generate();
            $cutoff = $this->mutate(function () use ($id, $credentials, $expiry, $overlap): ?string {
                $vendor = $this->lockedVendor($id);
                $this->assertKeyPrefix($vendor['api_key_prefix'], 'expected_key_prefix');
                $data = VendorKeyPolicy::rotation($vendor, $credentials, $expiry, $overlap);
                if (! (new ApiVendorModel())->update($id, $data + ['updated_by' => auth()->id()])) {
                    throw new \RuntimeException('Vendor key could not be rotated.');
                }
                (new AdminAuditService())->record('vendor.key_rotated', null, [
                    'vendor_id' => $id, 'vendor_code' => $vendor['vendor_code'], 'key_prefix' => $data['api_key_prefix'],
                    'api_key_expires_at' => $expiry, 'previous_key_prefix' => $data['previous_api_key_prefix'],
                    'previous_key_valid_until' => $data['previous_key_valid_until'], 'requested_overlap_minutes' => $overlap,
                    'ended_older_overlap_prefix' => $vendor['previous_api_key_prefix'] ?? null,
                ]);
                return $data['previous_key_valid_until'];
            });
            session()->setFlashdata('vendor_issued_key', ['id' => $id, 'key' => $credentials['key'], 'prefix' => $credentials['api_key_prefix']]);
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('success', 'New API key generated. ' . ($cutoff === null ? 'The previous key is no longer accepted.' : 'The previous key remains accepted until ' . $cutoff . ' (' . date_default_timezone_get() . ').') . ' Copy the new key below.');
        } catch (\InvalidArgumentException $exception) {
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Vendor key rotation failed for vendor {id}. Refresh and check the server configuration.', ['id' => $id]);
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', 'The key could not be rotated. It may have changed in another session; refresh and try again.');
        }
    }

    public function updateKeyExpiry(int $id): RedirectResponse
    {
        $this->findVendor($id);
        try {
            if ($this->request->getPost('api_key_expires_at') === null) { throw new \InvalidArgumentException('Provide an expiry date and time, or leave the field blank for no expiry.'); }
            $expiry = VendorKeyPolicy::expiration($this->request->getPost('api_key_expires_at'));
            $this->mutate(function () use ($id, $expiry): void {
                $vendor = $this->lockedVendor($id);
                $this->assertKeyPrefix($vendor['api_key_prefix'], 'expected_key_prefix');
                $data = ['api_key_expires_at' => $expiry, 'updated_by' => auth()->id()];
                if ($expiry !== null && ! empty($vendor['previous_key_valid_until']) && $expiry < $vendor['previous_key_valid_until']) { $data['previous_key_valid_until'] = $expiry; }
                if (! (new ApiVendorModel())->update($id, $data)) { throw new \RuntimeException('Expiry could not be saved.'); }
                (new AdminAuditService())->record('vendor.key_expiry_changed', null, ['vendor_id' => $id, 'vendor_code' => $vendor['vendor_code'], 'key_prefix' => $vendor['api_key_prefix'], 'previous_expires_at' => $vendor['api_key_expires_at'], 'api_key_expires_at' => $expiry, 'previous_key_valid_until' => $data['previous_key_valid_until'] ?? $vendor['previous_key_valid_until']]);
            });
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('success', 'API key expiry updated. Existing overlap deadlines are never extended.');
        } catch (\InvalidArgumentException $exception) {
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', $exception->getMessage());
        } catch (Throwable) {
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', 'The expiry could not be changed. Refresh and try again.');
        }
    }

    public function revokeKeyOverlap(int $id): RedirectResponse
    {
        $this->findVendor($id);
        try {
            $this->mutate(function () use ($id): void {
                $vendor = $this->lockedVendor($id);
                $this->assertKeyPrefix($vendor['previous_api_key_prefix'] ?? '', 'expected_previous_key_prefix');
                if (! (new ApiVendorModel())->update($id, ['previous_api_key_hash' => null, 'previous_api_key_prefix' => null, 'previous_key_valid_until' => null, 'previous_key_last_used_at' => null, 'updated_by' => auth()->id()])) { throw new \RuntimeException('Overlap could not be ended.'); }
                (new AdminAuditService())->record('vendor.key_overlap_revoked', null, ['vendor_id' => $id, 'vendor_code' => $vendor['vendor_code'], 'previous_key_prefix' => $vendor['previous_api_key_prefix']]);
            });
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('success', 'The previous key is no longer accepted. The current key continues to work until its expiry.');
        } catch (Throwable) {
            return redirect()->to(url_to('admin.vendors.edit', $id))->with('error', 'The overlap could not be ended. Refresh and try again.');
        }
    }

    private function lockedVendor(int $id): array
    {
        return db_connect()->query('SELECT * FROM api_vendors WHERE id = ? FOR UPDATE', [$id])->getRowArray() ?? throw new \RuntimeException('Vendor no longer exists.');
    }

    private function assertKeyPrefix(string $current, string $field): void
    {
        $expected = $this->request->getPost($field);
        if ($current === '' || ! is_string($expected) || ! hash_equals($current, $expected)) { throw new \RuntimeException('The key changed. Refresh and try again.'); }
    }

    private function validatedInput(bool $creating, ?array $vendor = null): ?array
    {
        $input = $this->request->getPost();
        $scopeSubmitted = array_key_exists('institution_access_mode', $input);
        $input['institution_access_mode'] ??= $vendor['institution_access_mode'] ?? 'restricted';
        $input['allowed_categories'] ??= [];
        $input['ingestion_categories'] ??= [];
        $categoryRule = static function ($value, $data, &$error): bool {
            $error = 'Select valid data categories and grant at least one consume or submit permission.';
            if (! is_array($value)) {
                return false;
            }
            foreach ($value as $category) {
                if (! is_string($category) || ! isset(VendorApiKeyService::CATEGORIES[$category])) {
                    return false;
                }
            }
            return ($data['allowed_categories'] ?? []) !== [] || ($data['ingestion_categories'] ?? []) !== [];
        };
        $rules = [
            'name' => 'required|max_length[150]',
            'contact_email' => 'permit_empty|valid_email|max_length[254]',
            'notes' => 'permit_empty|max_length[2000]',
            'is_active' => 'required|in_list[0,1]',
            'rate_limit_per_minute' => 'required|is_natural_no_zero|less_than_equal_to[10000]',
            'allowed_categories' => [$categoryRule],
            'ingestion_categories' => [$categoryRule],
            'institution_access_mode' => 'required|in_list[all,restricted]',
        ];
        if ($creating) {
            $rules['vendor_code'] = 'required|max_length[100]|regex_match[/^[a-zA-Z0-9._-]+$/]|is_unique[api_vendors.vendor_code]';
        }
        if (! $this->validateData($input, $rules)) {
            return null;
        }

        $scopeRules = [];
        if (! $scopeSubmitted && $vendor !== null) {
            $scopeRules = json_decode($vendor['institution_access_rules'] ?? '[]', true) ?? [];
        } elseif ($input['institution_access_mode'] === 'restricted') {
            $universities = $input['scope_university'] ?? [];
            $institutions = $input['scope_institution'] ?? [];
            if (! is_array($universities) || ! array_is_list($universities) || ! is_array($institutions) || ! array_is_list($institutions) || count($universities) !== count($institutions) || count($universities) > 200) {
                $this->validator->setError('institution_access_rules', 'Provide at most 200 paired university/institution access rules.');
                return null;
            }
            foreach ($universities as $index => $university) {
                if (! is_string($university) || ! is_string($institutions[$index])) {
                    $this->validator->setError('institution_access_rules', 'Access codes must be text values.');
                    return null;
                }
                if (trim($university) !== '' || trim($institutions[$index]) !== '') {
                    $scopeRules[] = ['university_code' => $university, 'institution_code' => $institutions[$index]];
                }
            }
        }
        try {
            $scopeRules = $input['institution_access_mode'] === 'all' ? [] : VendorInstitutionScope::canonicalRules($scopeRules);
            if ($input['institution_access_mode'] === 'restricted' && $scopeRules === []) {
                throw new \InvalidArgumentException('Restricted access requires at least one university/institution rule.');
            }
        } catch (\InvalidArgumentException $exception) {
            $this->validator->setError('institution_access_rules', $exception->getMessage());
            return null;
        }

        $data = [
            'name' => trim($input['name']),
            'contact_email' => trim($input['contact_email'] ?? '') ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
            'is_active' => (int) $input['is_active'],
            'rate_limit_per_minute' => (int) $input['rate_limit_per_minute'],
            'allowed_categories' => json_encode(array_values(array_unique($input['allowed_categories']))),
            'ingestion_categories' => json_encode(array_values(array_unique($input['ingestion_categories']))),
            'institution_access_mode' => $input['institution_access_mode'],
            'institution_access_rules' => json_encode($scopeRules),
        ];
        if ($creating) {
            $data['vendor_code'] = trim($input['vendor_code']);
            try {
                $data['api_key_expires_at'] = VendorKeyPolicy::expiration($input['api_key_expires_at'] ?? (new \DateTimeImmutable('+' . VendorKeyPolicy::DEFAULT_LIFETIME_DAYS . ' days'))->format('Y-m-d\TH:i'));
            } catch (\InvalidArgumentException $exception) {
                $this->validator->setError('api_key_expires_at', $exception->getMessage());
                return null;
            }
        }
        return $data;
    }

    private function findVendor(int $id): array
    {
        $vendor = (new ApiVendorModel())->find($id);
        if ($vendor === null) {
            throw PageNotFoundException::forPageNotFound('Vendor not found.');
        }
        return $vendor;
    }

    private function mutate(callable $callback): mixed
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $result = $callback();
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Vendor change could not be committed.');
            }
            return $result;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
}
