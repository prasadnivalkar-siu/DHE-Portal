<?php

namespace Tests\Unit;

use App\Services\AdmissionIntakeReportService;
use PHPUnit\Framework\TestCase;

final class AdmissionIntakeReportServiceTest extends TestCase
{
    public function testNormalizesInstitutionAcronymsAndProgramCodes(): void
    {
        self::assertSame(
            AdmissionIntakeReportService::normalizeInstitution('Symbiosis Centre for Media and Communication'),
            AdmissionIntakeReportService::normalizeInstitution('Symbiosis Centre for Media and Communication (SCMC)'),
        );
        self::assertSame('30142', AdmissionIntakeReportService::normalizeProgramCode('030142'));
        self::assertSame('30142', AdmissionIntakeReportService::normalizeProgramCode('30142'));
        self::assertSame('MBA-01', AdmissionIntakeReportService::normalizeProgramCode(' mba-01 '));
        self::assertSame('', AdmissionIntakeReportService::normalizeProgramCode('NA'));
    }

    public function testCombinesMatchingProgramsAndKeepsUnmatchedRowsVisible(): void
    {
        $service = new AdmissionIntakeReportService($this->createMock(\CodeIgniter\Database\BaseConnection::class));
        $rows = $service->combine([
            [
                'academic_year' => '2024-25', 'institution_code' => 'NA',
                'college_name' => 'Example Institute (EI)', 'program_code' => '030142',
                'program_name' => 'Master of Business Administration (Communication Management)',
                'admission_count' => 45,
            ],
            [
                'academic_year' => '2024-25', 'institution_code' => 'NA',
                'college_name' => 'Example Institute (EI)', 'program_code' => 'MSC-1',
                'program_name' => 'Master of Science (Data Science)', 'admission_count' => 12,
            ],
        ], [
            [
                'academic_year' => '2024-25', 'institution_code' => 'C-1',
                'institution_name' => 'Example Institute', 'program_code' => '30142',
                'program_name' => 'M.B.A. (Communication Management)',
                'course_name' => 'Management', 'total_intake' => 60,
            ],
            [
                'academic_year' => '2024-25', 'institution_code' => 'C-1',
                'institution_name' => 'Example Institute', 'program_code' => 'BBA-1',
                'program_name' => 'Master of Science (Data Science)',
                'course_name' => 'Management', 'total_intake' => 30,
            ],
        ]);

        self::assertCount(3, $rows);
        $matched = array_values(array_filter($rows, static fn (array $row): bool => $row['match_status'] === 'matched'));
        self::assertCount(1, $matched);
        self::assertSame(60, $matched[0]['intake_total']);
        self::assertSame('030142', $matched[0]['program_code']);
        self::assertSame('C-1', $matched[0]['institution_code']);
        self::assertSame(45, $matched[0]['admission_count']);
        self::assertSame(15, $matched[0]['available_seats']);
        self::assertSame(75.0, $matched[0]['utilization']);
        self::assertCount(1, array_filter($rows, static fn (array $row): bool => $row['match_status'] === 'missing_intake'));
        self::assertCount(1, array_filter($rows, static fn (array $row): bool => $row['match_status'] === 'no_admissions'));
    }

    public function testDoesNotMatchEquivalentDegreeNamesWithoutMatchingCodes(): void
    {
        $service = new AdmissionIntakeReportService($this->createMock(\CodeIgniter\Database\BaseConnection::class));
        $rows = $service->combine([[
            'academic_year' => '2024-25', 'institution_code' => 'C-1',
            'college_name' => 'Example Institute', 'program_code' => 'MBA-1',
            'program_name' => 'Master of Business Administration', 'admission_count' => 10,
        ]], [[
            'academic_year' => '2024-25', 'institution_code' => 'C-1',
            'institution_name' => 'Example Institute', 'program_code' => 'MBA-2',
            'program_name' => 'M.B.A.', 'course_name' => 'Management', 'total_intake' => 20,
        ]]);

        self::assertCount(2, $rows);
        self::assertCount(1, array_filter($rows, static fn (array $row): bool => $row['match_status'] === 'missing_intake'));
        self::assertCount(1, array_filter($rows, static fn (array $row): bool => $row['match_status'] === 'no_admissions'));
    }
}
