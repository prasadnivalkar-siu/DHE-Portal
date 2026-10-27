<?php

namespace Tests\Unit;

use App\Libraries\ExaminationSchema;
use PHPUnit\Framework\TestCase;

final class ExaminationSchemaTest extends TestCase
{
    public function testMapsAllFortyFourDictionaryHeadersAndTemplateVariants(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExaminationSchema::HEADERS));
        $headers[array_search('UNIV_AISHE_CODE', $headers, true)] = 'Univ_AISHE_Code';
        $headers[array_search('SESSION', $headers, true)] = 'session';
        $headers[array_search('COLLEGE/STUDY_CENTER_CODE', $headers, true)] = 'college/study_center_code';

        $result = ExaminationSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(44, $result['map']);
        self::assertArrayHasKey('exam_session', $result['map']);
        self::assertArrayHasKey('college_study_center_code', $result['map']);
    }

    public function testNormalizesBlankAndDashCellsToNa(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExaminationSchema::HEADERS));
        $map = ExaminationSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'univ_aishe_code' => 'V-TEST',
            'academic_year' => '2021-2022',
            'prn' => 'ABCD123456',
            'exam_year' => '-',
            'course_name' => 'Test course',
            'inward_date' => '-',
            'inward_time' => '',
            'last_update_date' => '-',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = ExaminationSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('NA', $result['data']['exam_year']);
        self::assertSame('NA', $result['data']['inward_date']);
        self::assertSame('NA', $result['data']['inward_time']);
        self::assertSame('NA', $result['data']['last_update_date']);
        self::assertSame('NA', $result['data']['paper_code']);
    }

    public function testNormalizesExaminationDatesAndTimes(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExaminationSchema::HEADERS));
        $map = ExaminationSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'univ_aishe_code' => 'V-TEST',
            'academic_year' => '2021-2022',
            'seat_number' => 'SEAT-9',
            'inward_date' => '15-Oct-2021',
            'inward_time' => '2:35 PM',
            'last_update_date' => '2026-09-23T14:35:10+05:30',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = ExaminationSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('2021-10-15', $result['data']['inward_date']);
        self::assertSame('14:35:00', $result['data']['inward_time']);
        self::assertSame('2026-09-23 14:35:10', $result['data']['last_update_date']);
    }

    public function testRecordIdentityIncludesNormalizedPaperNameWhileRowHashTracksOtherChanges(): void
    {
        $first = [
            'academic_year' => '2021-2022', 'univ_aishe_code' => 'V-TEST', 'exam_year' => '2022',
            'exam_session' => 'Summer', 'course_code' => 'BSC', 'course_term' => '2', 'branch' => 'Science',
            'admission_form_no' => 'FORM-1', 'prn' => 'PRN-1', 'exam_form_number' => 'EX-1',
            'seat_number' => 'S-1', 'paper_code' => 'P-1', 'assessment_type' => 'Theory',
            'paper_name' => 'Physics',
        ];
        $differentPaper = array_merge($first, ['paper_name' => 'Advanced Physics']);
        $equivalentPaper = array_merge($first, ['paper_name' => ' physics ']);
        $changedNonKeyField = array_merge($first, ['center_name' => 'Updated examination centre']);

        self::assertNotSame(ExaminationSchema::recordKey($first), ExaminationSchema::recordKey($differentPaper));
        self::assertSame(ExaminationSchema::recordKey($first), ExaminationSchema::recordKey($equivalentPaper));
        self::assertSame(ExaminationSchema::recordKey($first), ExaminationSchema::recordKey($changedNonKeyField));
        self::assertNotSame(ExaminationSchema::rowHash($first), ExaminationSchema::rowHash($changedNonKeyField));
    }

    public function testApiSerializationUsesDictionaryNamesWithoutInternalId(): void
    {
        $result = ExaminationSchema::toApi([
            'id' => 51,
            'univ_aishe_code' => 'V-TEST',
            'academic_year' => '2021-2022',
            'exam_session' => 'Summer',
            'prn' => 'ABCD123456',
        ]);

        self::assertCount(44, $result);
        self::assertArrayNotHasKey('clientid', $result);
        self::assertSame('V-TEST', $result['UNIV_AISHE_CODE']);
        self::assertSame('Summer', $result['SESSION']);
        self::assertSame('NA', $result['PAPER_CODE']);
        self::assertArrayHasKey('COLLEGE/STUDY_CENTER_NAME', $result);
    }
}
