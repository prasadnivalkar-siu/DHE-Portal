<?php

namespace App\Services;

/** Request-scoped identity, established only by ApiKeyFilter. */
final class VendorApiContext
{
    private ?array $vendor = null;

    public function set(?array $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function vendorCode(): string
    {
        if ($this->vendor === null) {
            throw new \LogicException('Vendor API authentication is required.');
        }

        return $this->vendor['vendor_code'];
    }

    public function rateLimit(): int
    {
        $this->vendorCode();

        return max(1, (int) $this->vendor['rate_limit_per_minute']);
    }

    public function vendorId(): int
    {
        $this->vendorCode();
        return (int) $this->vendor['id'];
    }

    public function institutionScope(): VendorInstitutionScope
    {
        $this->vendorCode();
        return new VendorInstitutionScope($this->vendor);
    }
}
