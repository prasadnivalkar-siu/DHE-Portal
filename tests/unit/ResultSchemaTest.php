<?php

namespace Tests\Unit;

use App\Libraries\ResultSchema;
use PHPUnit\Framework\TestCase;

final class ResultSchemaTest extends TestCase
{
    public function testMapsAllFourHundredFortyTemplateHeadersAndSeparatorVariants(): void
    {
        $headers = ResultSchema::headers();
        $headers[array_search('COURSE_SUBTITLE' . "\xC2\xA0", $headers, true)] = 'COURSE_SUBTITLE';
        $headers[array_search('WITHHELD STATUS', $headers, true)] = 'WITHHELD_STATUS';

        $result = ResultSchema::mapHeaders($headers);

        self::assertCount(440, ResultSchema::headers());
        self::assertCount(440, $result['map']);
        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
    }

    public function testNormalizesBlankDashAndExcelDates(): void
    {
        $headers = ResultSchema::headers();
        $map = ResultSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'UNIV_AISHE_CODE' => 'U-0318',
            'SESSION' => '2024-25 ',
            'YEAR' => 2024,
            'SEASON' => 'Summer',
            'PRN' => '202490110008',
            'ORG_NAME' => '-',
            'DOR' => 44623,
            'LAST_UPDATE_DATE' => 44617,
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = ResultSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('2024-25', $result['data']['SESSION']);
        self::assertSame('2024', $result['data']['YEAR']);
        self::assertSame('NA', $result['data']['ORG_NAME']);
        self::assertSame('2022-03-03', $result['data']['DOR']);
        self::assertSame('2022-02-25 00:00:00', $result['data']['LAST_UPDATE_DATE']);
    }

    public function testAcceptsZeroInResultMilestoneAndLastUpdateDates(): void
    {
        $headers = ResultSchema::headers();
        $map = ResultSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'UNIV_AISHE_CODE' => 'U-0318',
            'SESSION' => '2024-25',
            'YEAR' => 2024,
            'SEASON' => 'Summer',
            'PRN' => '202490110008',
            'DOI' => 0,
            'DOV' => '0',
            'DOP' => 0,
            'DOQ' => '0',
            'DOS' => 0,
            'LAST_UPDATE_DATE' => '0',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = ResultSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        foreach (['DOI', 'DOV', 'DOP', 'DOQ', 'DOS', 'LAST_UPDATE_DATE'] as $field) {
            self::assertSame('0', $result['data'][$field]);
        }
    }

    public function testCanonicalizesSeasonWithTrailingYear(): void
    {
        self::assertSame('Winter', ResultSchema::normalizeSeason('Winter'));
        self::assertSame('Winter', ResultSchema::normalizeSeason('Winter 2021'));
        self::assertSame('Winter', ResultSchema::normalizeSeason('Winter 2021-22'));
        self::assertSame('Summer', ResultSchema::normalizeSeason('Summer'));
        self::assertSame('Summer', ResultSchema::normalizeSeason('Summer 2021'));
        self::assertSame('Summer', ResultSchema::normalizeSeason('Summer 2021-22'));

        $data = array_fill_keys(ResultSchema::headers(), 'NA');
        $data['SEASON'] = 'Winter 2021';
        $stored = ResultSchema::toDatabase($data);
        $api = ResultSchema::toApi(['result_payload' => $stored['result_payload']]);

        self::assertSame('Winter', $stored['season']);
        self::assertSame('Winter', $api['SEASON']);
    }

    public function testCanonicalizesAcademicYearFamily(): void
    {
        self::assertSame('2021-22', ResultSchema::canonicalAcademicYear('2021-22'));
        self::assertSame('2021-22', ResultSchema::canonicalAcademicYear('2021-23'));
        self::assertSame('2021-22', ResultSchema::canonicalAcademicYear('2021-24'));
        self::assertSame('2021-22', ResultSchema::canonicalAcademicYear('2021-25'));
        self::assertSame('2021-22', ResultSchema::canonicalAcademicYear('2021-26'));
        self::assertSame('2024-25', ResultSchema::canonicalAcademicYear('2024-2025'));
    }

    public function testRejectsMissingDatasetAndStudentIdentity(): void
    {
        $headers = ResultSchema::headers();
        $map = ResultSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        $row[$map['SESSION']] = '2024-25';
        $row[$map['YEAR']] = 'invalid';

        $result = ResultSchema::normalizeRow($row, $map);
        $fields = array_column($result['errors'], 'field');

        self::assertContains('UNIV_AISHE_CODE', $fields);
        self::assertContains('SEASON', $fields);
        self::assertContains('Student identifier', $fields);
        self::assertContains('YEAR', $fields);
    }

    public function testRecordIdentityIsStableWhileRowHashTracksResultChanges(): void
    {
        $first = [
            'SESSION' => '2024-25', 'UNIV_AISHE_CODE' => 'U-0318', 'ORG_AISHE_CODE' => 'C-0123',
            'YEAR' => '2024', 'SEASON' => 'Summer', 'PRN' => '202490110008', 'REGN_NO' => 'R-1',
            'RROLL' => '10', 'COURSE_CODE' => 'MA', 'SEM' => '2', 'EXAM_TYPE' => 'Theory',
            'TERM_TYPE' => 'Sem', 'RESULT' => 'Pass',
        ];
        $second = array_merge($first, ['RESULT' => 'Fail']);

        self::assertSame(ResultSchema::recordKey($first), ResultSchema::recordKey($second));
        self::assertNotSame(ResultSchema::rowHash($first), ResultSchema::rowHash($second));
    }

    public function testCompactStorageRoundTripsAllApiFields(): void
    {
        $data = array_fill_keys(ResultSchema::headers(), 'NA');
        $data['UNIV_AISHE_CODE'] = 'U-0318';
        $data['SESSION'] = '2024-25';
        $data['YEAR'] = '2024';
        $data['SEASON'] = 'Summer';
        $data['PRN'] = '202490110008';
        $data['RESULT'] = 'Pass';
        $stored = ResultSchema::toDatabase($data);
        $api = ResultSchema::toApi(['id' => 51, 'result_payload' => $stored['result_payload']]);

        self::assertSame('2024-25', $stored['academic_year']);
        self::assertSame('2024', $stored['exam_year']);
        self::assertSame('Summer', $stored['season']);
        self::assertCount(440, $api);
        self::assertArrayNotHasKey('clientid', $api);
        self::assertSame('Pass', $api['RESULT']);
        self::assertArrayHasKey('SUB15_Remark', $api);
        self::assertArrayHasKey('LAST_UPDATE_DATE', $api);
    }
}
