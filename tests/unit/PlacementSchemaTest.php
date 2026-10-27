<?php

namespace Tests\Unit;

use App\Libraries\PlacementSchema;
use PHPUnit\Framework\TestCase;

final class PlacementSchemaTest extends TestCase
{
    public function testMapsAllFourteenTemplateHeadersAndProgramAliases(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], PlacementSchema::HEADERS));
        $headers[array_search('ProgramName', $headers, true)] = 'Program Name';
        $headers[array_search('CourseName', $headers, true)] = 'Course_Name';

        $result = PlacementSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(14, $result['map']);
        self::assertArrayHasKey('company_name', $result['map']);
        self::assertArrayHasKey('package_above_15_lakh', $result['map']);
    }

    public function testNormalizesWhitespaceBlankAndDashValuesAndPackageCounts(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], PlacementSchema::HEADERS));
        $map = PlacementSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'academic_year' => '2024-25  ',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'company_name' => 'Example Employer',
            'program_name' => '-',
            'package_1_to_3_lakh' => '16',
            'package_3_to_5_lakh' => '0',
            'package_5_to_10_lakh' => '-',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = PlacementSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('2024-25', $result['data']['academic_year']);
        self::assertSame('NA', $result['data']['program_name']);
        self::assertSame(16, $result['data']['package_1_to_3_lakh']);
        self::assertSame(0, $result['data']['package_3_to_5_lakh']);
        self::assertSame('NA', $result['data']['package_5_to_10_lakh']);
    }

    public function testRejectsMissingCompanyAndInvalidPackageCounts(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], PlacementSchema::HEADERS));
        $map = PlacementSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        $row[$map['academic_year']] = '2024-25';
        $row[$map['university_aishe_code']] = 'U-TEST';
        $row[$map['institution_aishe_code']] = 'C-100';
        $row[$map['package_1_to_3_lakh']] = '-1';
        $row[$map['package_3_to_5_lakh']] = 'two';

        $result = PlacementSchema::normalizeRow($row, $map);

        self::assertCount(3, $result['errors']);
        self::assertSame(
            ['Company Name', 'Package range (1 to 3 Lakh)', 'Package range (3 to 5 Lakh)'],
            array_column($result['errors'], 'field'),
        );
    }

    public function testRecordIdentityIsStableWhileRowHashTracksPackageChanges(): void
    {
        $first = [
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'program_name' => 'B.A.',
            'course_name' => 'Arts',
            'company_name' => 'Example Employer',
            'package_1_to_3_lakh' => 16,
        ];
        $second = array_merge($first, ['package_1_to_3_lakh' => 18]);

        self::assertSame(PlacementSchema::recordKey($first), PlacementSchema::recordKey($second));
        self::assertNotSame(PlacementSchema::rowHash($first), PlacementSchema::rowHash($second));
    }

    public function testApiSerializationUsesTemplateNamesWithoutInternalId(): void
    {
        $result = PlacementSchema::toApi([
            'id' => 51,
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'company_name' => 'Example Employer',
            'package_1_to_3_lakh' => '16',
            'package_above_15_lakh' => '0',
        ]);

        self::assertCount(14, $result);
        self::assertArrayNotHasKey('clientid', $result);
        self::assertSame('Example Employer', $result['Company Name']);
        self::assertSame(16, $result['Package range (1 to 3 Lakh)']);
        self::assertSame(0, $result['Package range More than 15 Lakh']);
        self::assertSame('NA', $result['CourseName']);
        self::assertArrayHasKey('University / College AISHE code', $result);
    }
}
