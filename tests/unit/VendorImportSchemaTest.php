<?php

namespace Tests\Unit;

use App\Libraries\VendorImportSchema;
use PHPUnit\Framework\TestCase;

final class VendorImportSchemaTest extends TestCase
{
    public static function validRecords(): iterable
    {
        yield 'admissions' => ['admissions', ['Academic_Year' => '2024-25', 'Univ_Code' => 'U-1234', 'College_Code' => 'C-12', 'StudentID' => 'student-1']];
        yield 'examinations' => ['examinations', ['ACADEMIC_YEAR' => '2024-25', 'UNIV_AISHE_CODE' => 'U-1234', 'PRN' => 'student-1']];
        yield 'intake' => ['intake', ['Academic Year' => '2024-25', 'University AISHE Code' => 'U-1234', 'University / College AISHE code' => 'C-12']];
        yield 'expenses' => ['expenses', ['Financial year' => '2024-25', 'University AISHE Code' => 'U-1234', 'AISHE Code' => 'C-12']];
        yield 'placements' => ['placements', ['Academic Year' => '2024-25', 'University AISHE Code' => 'U-1234', 'University / College AISHE code' => 'C-12', 'Company Name' => 'Company']];
        yield 'results' => ['results', ['SESSION' => '2024-25', 'UNIV_AISHE_CODE' => 'U-1234', 'YEAR' => '2024', 'SEASON' => 'Winter 2024', 'PRN' => 'student-1']];
    }

    /** @dataProvider validRecords */
    public function testEveryCategoryReusesExcelNormalization(string $category, array $record): void
    {
        $result = VendorImportSchema::normalize($category, $record);
        self::assertSame([], $result['errors']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['data']['record_key']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['data']['row_hash']);
        self::assertArrayNotHasKey('publication_status', $result['data']);
        if ($category === 'results') {
            self::assertSame('Winter', $result['data']['season']);
            self::assertCount(440, json_decode($result['data']['result_payload'], true));
        }
    }

    public function testBlankDashAndLeadingZeroHandling(): void
    {
        $record = iterator_to_array(self::validRecords())['admissions'][1];
        $result = VendorImportSchema::normalize('admissions', $record + ['Program_code' => '0007', 'Gender' => '-', 'Caste' => null]);
        self::assertSame([], $result['errors']);
        self::assertSame('0007', $result['data']['program_code']);
        self::assertSame('NA', $result['data']['gender']);
        self::assertSame('NA', $result['data']['caste']);
    }

    public function testRequiredAdmissionIdentityIsNotReplacedByNa(): void
    {
        $result = VendorImportSchema::normalize('admissions', ['StudentID' => 'student-1']);
        self::assertNotEmpty($result['errors']);
    }

    public function testProtectedFieldsNestedValuesAndAliasesAreRejected(): void
    {
        $record = iterator_to_array(self::validRecords())['admissions'][1];
        foreach ([['publication_status' => 'published'], ['import_job_id' => 7], ['Gender' => ['bad']], ['Academic Year' => '2024-25']] as $invalid) {
            self::assertNotEmpty(VendorImportSchema::normalize('admissions', $record + $invalid)['errors']);
        }
    }

    public function testRequestHashIgnoresObjectKeyOrderButRetainsRecordOrder(): void
    {
        $a = ['StudentID' => 'one', 'Academic_Year' => '2024-25'];
        $b = ['StudentID' => 'two', 'Academic_Year' => '2024-25'];
        self::assertSame(VendorImportSchema::requestHash([$a]), VendorImportSchema::requestHash([array_reverse($a, true)]));
        self::assertNotSame(VendorImportSchema::requestHash([$a, $b]), VendorImportSchema::requestHash([$b, $a]));
    }
}
