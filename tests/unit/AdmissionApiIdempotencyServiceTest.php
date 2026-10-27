<?php

namespace Tests\Unit;

use App\Services\AdmissionApiIdempotencyService;
use PHPUnit\Framework\TestCase;

final class AdmissionApiIdempotencyServiceTest extends TestCase
{
    public function testRequestHashIsStableAcrossPayloadKeyOrder(): void
    {
        $service = new AdmissionApiIdempotencyService();
        $first = [
            'Vendor_Code' => 'vendor-01',
            'Academic_Year' => '2024-25',
            'PageNo' => 1,
        ];
        $second = [
            'PageNo' => 1,
            'Academic_Year' => '2024-25',
            'Vendor_Code' => 'vendor-01',
        ];

        self::assertSame($service->requestHash($first), $service->requestHash($second));
    }

    public function testRequestHashChangesWhenDeliveryParametersChange(): void
    {
        $service = new AdmissionApiIdempotencyService();
        $first = ['Vendor_Code' => 'vendor-01', 'PageNo' => 1, 'PageSize' => 1000];
        $second = ['Vendor_Code' => 'vendor-01', 'PageNo' => 2, 'PageSize' => 1000];

        self::assertNotSame($service->requestHash($first), $service->requestHash($second));
    }
}
