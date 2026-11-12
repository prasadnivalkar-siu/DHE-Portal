<?php

namespace Tests\Unit;

use App\Libraries\VendorActivityFilters;
use App\Services\VendorActivityDashboard;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VendorActivityTest extends TestCase
{
    public function testDefaultRangeContainsThirtyCalendarDaysAcrossMonthBoundary(): void
    {
        $filters = VendorActivityFilters::normalize([], new DateTimeImmutable('2026-11-05'));
        self::assertSame('2026-10-07', $filters['date_from']);
        self::assertSame('2026-11-05', $filters['date_to']);
        self::assertNull($filters['vendor_id']);
        self::assertSame(1, $filters['page']);
    }

    public static function invalidFilters(): iterable
    {
        yield 'array vendor' => [['vendor_id' => []]];
        yield 'unknown category' => [['category' => 'anything']];
        yield 'negative vendor' => [['vendor_id' => '-1']];
        yield 'fractional vendor' => [['vendor_id' => '1.2']];
        yield 'invalid calendar date' => [['date_from' => '2026-02-30']];
        yield 'date not padded' => [['date_from' => '2026-1-1']];
        yield 'backward range' => [['date_from' => '2026-11-06', 'date_to' => '2026-11-05']];
        yield 'excessive range' => [['date_from' => '2025-01-01', 'date_to' => '2026-11-05']];
    }

    /** @dataProvider invalidFilters */
    public function testMalformedFiltersAreRejected(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        VendorActivityFilters::normalize($input, new DateTimeImmutable('2026-11-05'));
    }

    public function testOneDayRangeIsInclusiveAndPreservesSelectedVendorCategory(): void
    {
        $filters = VendorActivityFilters::normalize(['vendor_id' => '7', 'category' => 'results', 'date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'page' => '-1']);
        self::assertSame(7, $filters['vendor_id']);
        self::assertSame('results', $filters['category']);
        self::assertSame(1, $filters['page']);
        self::assertCount(1, VendorActivityDashboard::trend([], $filters['date_from'], $filters['date_to']));
    }

    public function testDailyTrendIncludesZerosAndPreservesFailures(): void
    {
        $trend = VendorActivityDashboard::trend([['day' => '2026-11-04', 'requests' => 5, 'failed' => 2]], '2026-11-03', '2026-11-05');
        self::assertSame([0, 5, 0], array_column($trend, 'requests'));
        self::assertSame([0, 2, 0], array_column($trend, 'failed'));
    }

    public function testWeeklyTrendKeepsPartialWeeksAtBothBoundaries(): void
    {
        $trend = VendorActivityDashboard::trend([
            ['day' => '2026-10-01', 'requests' => 4, 'failed' => 1],
            ['day' => '2026-10-02', 'requests' => 3, 'failed' => 0],
            ['day' => '2026-11-05', 'requests' => 2, 'failed' => 2],
        ], '2026-10-01', '2026-11-05');
        self::assertSame('2026-09-28', $trend[0]['day']);
        self::assertSame(7, $trend[0]['requests']);
        self::assertSame(9, array_sum(array_column($trend, 'requests')));
        self::assertSame(3, array_sum(array_column($trend, 'failed')));
    }
}
