<?php

namespace Tests\Unit;

use App\Libraries\VendorKeyPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VendorKeyPolicyTest extends TestCase
{
    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-11-05 12:00:00'); }
    private function vendor(): array { return ['api_key_hash' => 'old-hash', 'api_key_prefix' => 'old-prefix', 'api_key_expires_at' => null, 'previous_api_key_hash' => 'oldest-hash', 'current_key_last_used_at' => '2026-11-05 11:00:00']; }
    private function credentials(): array { return ['api_key_hash' => 'new-hash', 'api_key_prefix' => 'new-prefix', 'api_key_encrypted' => 'encrypted-replacement']; }

    public function testExpiryIsExclusiveAndMalformedValuesNeverGrantAccess(): void
    {
        self::assertTrue(VendorKeyPolicy::usable(null, $this->now()));
        self::assertTrue(VendorKeyPolicy::usable('2026-11-05 12:00:01', $this->now()));
        foreach (['2026-11-05 12:00:00', '2026-11-05 11:59:59', '', 'not-a-date', '2026-02-30 12:00:00'] as $date) { self::assertFalse(VendorKeyPolicy::usable($date, $this->now())); }
    }

    public function testFormExpiryUsesStrictFutureLocalDateTimes(): void
    {
        self::assertSame('2026-11-06 13:15:00', VendorKeyPolicy::expiration('2026-11-06T13:15', $this->now()));
        self::assertSame('2026-11-06 13:15:42', VendorKeyPolicy::expiration('2026-11-06T13:15:42', $this->now()));
        self::assertNull(VendorKeyPolicy::expiration('', $this->now()));
    }

    public static function invalidExpirations(): iterable
    {
        foreach (['2026-02-30T12:00', '2026-11-05T12:00', '2026-11-04T12:00', '2026-11-06', '2026-11-06T12:00Z', 'tomorrow', []] as $value) { yield [$value]; }
    }

    /** @dataProvider invalidExpirations */
    public function testInvalidExpiryIsRejected(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        VendorKeyPolicy::expiration($value, $this->now());
    }

    public function testOverlapRetainsOnlyTheImmediatelyPreviousHash(): void
    {
        $data = VendorKeyPolicy::rotation($this->vendor(), $this->credentials(), '2027-02-01 00:00:00', 60, $this->now());
        self::assertSame('new-hash', $data['api_key_hash']);
        self::assertSame('old-hash', $data['previous_api_key_hash']);
        self::assertSame('2026-11-05 13:00:00', $data['previous_key_valid_until']);
        self::assertNull($data['current_key_last_used_at']);
        self::assertSame('2026-11-05 11:00:00', $data['previous_key_last_used_at']);
        self::assertStringNotContainsString('oldest-hash', json_encode($data));
    }

    public function testOverlapNeverExtendsEitherKeysExpiry(): void
    {
        $vendor = array_replace($this->vendor(), ['api_key_expires_at' => '2026-11-05 12:10:00']);
        self::assertSame('2026-11-05 12:10:00', VendorKeyPolicy::rotation($vendor, $this->credentials(), null, 60, $this->now())['previous_key_valid_until']);
        self::assertSame('2026-11-05 12:05:00', VendorKeyPolicy::rotation($vendor, $this->credentials(), '2026-11-05 12:05:00', 60, $this->now())['previous_key_valid_until']);
    }

    public function testImmediateRotationAndExpiredKeysDoNotReceiveOverlap(): void
    {
        self::assertNull(VendorKeyPolicy::rotation($this->vendor(), $this->credentials(), null, 0, $this->now())['previous_api_key_hash']);
        $expired = array_replace($this->vendor(), ['api_key_expires_at' => '2026-11-05 12:00:00']);
        self::assertNull(VendorKeyPolicy::rotation($expired, $this->credentials(), null, 1440, $this->now())['previous_api_key_hash']);
    }

    public static function invalidOverlaps(): iterable
    {
        foreach ([-1, 1441, '1.5', true, [], ''] as $value) { yield [$value]; }
    }

    /** @dataProvider invalidOverlaps */
    public function testOverlapIsBoundedAndCannotUseFractionalMinutes(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        VendorKeyPolicy::overlapMinutes($value);
    }
}
