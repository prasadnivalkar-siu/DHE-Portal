<?php

namespace Tests\Unit;

use App\Libraries\IntakeSchema;
use PHPUnit\Framework\TestCase;

final class IntakeSchemaTest extends TestCase
{
    public function testMapsLegacyFourteenColumnTemplateAndTemporaryAlias(): void
    {
        $definitions = IntakeSchema::HEADERS;
        unset($definitions['program_code']);
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], $definitions));
        $headers[array_search('Faculty (Temporory)', $headers, true)] = 'Faculty (Temporary)';

        $result = IntakeSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(14, $result['map']);
        self::assertArrayNotHasKey('program_code', $result['map']);
        self::assertArrayHasKey('institution_aishe_code', $result['map']);
        self::assertArrayHasKey('faculty_temporary', $result['map']);
    }

    public function testMapsNewFifteenColumnTemplateWithOptionalProgrammeCode(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], IntakeSchema::HEADERS));

        $result = IntakeSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(15, $result['map']);
        self::assertArrayHasKey('program_code', $result['map']);
    }

    public function testNormalizesBlankAndDashCellsToNaAndCountsToIntegers(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], IntakeSchema::HEADERS));
        $map = IntakeSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'university_name' => '-',
            'program_name' => 'B.Ed',
            'total_intake' => '110',
            'faculty_permanent' => '5',
            'faculty_temporary' => '3',
            'faculty_chb' => '0',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = IntakeSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('NA', $result['data']['program_code']);
        self::assertSame('NA', $result['data']['university_name']);
        self::assertSame('NA', $result['data']['course_name']);
        self::assertSame(110, $result['data']['total_intake']);
        self::assertSame(3, $result['data']['faculty_temporary']);
        self::assertSame(0, $result['data']['faculty_chb']);
    }

    public function testRejectsMissingIdentityAndInvalidCountValues(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], IntakeSchema::HEADERS));
        $map = IntakeSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        $row[$map['academic_year']] = '2024-25';
        $row[$map['university_aishe_code']] = 'U-TEST';
        $row[$map['total_intake']] = '-4';
        $row[$map['faculty_permanent']] = 'two';

        $result = IntakeSchema::normalizeRow($row, $map);

        self::assertCount(3, $result['errors']);
        self::assertSame(
            ['University / College AISHE code', 'TotalIntake', 'Faculty (Permanent)'],
            array_column($result['errors'], 'field'),
        );
    }

    public function testRecordIdentityIsStableWhileRowHashTracksCountChanges(): void
    {
        $first = [
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'program_name' => 'B.Ed',
            'course_name' => 'Education',
            'level' => 'UG',
            'designation' => 'Assistant Professor',
            'total_intake' => 110,
        ];
        $second = array_merge($first, ['total_intake' => 120]);

        self::assertSame(IntakeSchema::recordKey($first), IntakeSchema::recordKey($second));
        self::assertNotSame(IntakeSchema::rowHash($first), IntakeSchema::rowHash($second));
    }

    public function testApiSerializationUsesTemplateNamesWithoutInternalId(): void
    {
        $result = IntakeSchema::toApi([
            'id' => 51,
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'program_code' => '030142',
            'total_intake' => '110',
            'faculty_temporary' => '3',
        ]);

        self::assertCount(15, $result);
        self::assertArrayNotHasKey('clientid', $result);
        self::assertSame('U-TEST', $result['University AISHE Code']);
        self::assertSame('030142', $result['Programme Code']);
        self::assertSame(110, $result['TotalIntake']);
        self::assertSame(3, $result['Faculty (Temporory)']);
        self::assertSame('NA', $result['Course Name']);
        self::assertArrayHasKey('University / College AISHE code', $result);
    }
}
