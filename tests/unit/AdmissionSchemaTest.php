<?php

namespace Tests\Unit;

use App\Libraries\AdmissionSchema;
use PHPUnit\Framework\TestCase;

final class AdmissionSchemaTest extends TestCase
{
    public function testMapsAllTemplateHeadersAndLegacyVariants(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], AdmissionSchema::HEADERS));
        $prnIndex = array_search('PRN', $headers, true);
        $headers[$prnIndex] = "PRN\u{00A0}";

        $result = AdmissionSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(45, $result['map']);
        self::assertArrayHasKey('student_city_village', $result['map']);
        self::assertArrayHasKey('prn', $result['map']);
    }

    public function testAcceptsCorrectedStudentCityAndApaarHeaders(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], AdmissionSchema::HEADERS));
        $headers[array_search('Stident_City/Village', $headers, true)] = 'Student_City/Village';
        $headers[array_search('ABC ID', $headers, true)] = 'APAAR ID (ABC ID)';

        $result = AdmissionSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
    }

    public function testNormalizesDatesAmountsAndPendingCount(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], AdmissionSchema::HEADERS));
        $map = AdmissionSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        $values = [
            'academic_year' => '2024-25',
            'univ_code' => 'U-1234',
            'college_code' => 'C-1007',
            'admission_application_form_no' => 'FORM-1',
            'admission_date' => 'Aug 12, 2022',
            'eligibility_date' => '15-Oct-2021',
            'receipt_amount' => '1,234.50',
        ];
        foreach ($values as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = AdmissionSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('2022-08-12', $result['data']['admission_date']);
        self::assertSame('2021-10-15', $result['data']['eligibility_date']);
        self::assertSame('1234.50', $result['data']['receipt_amount']);
        self::assertSame('NA', $result['data']['pending_documents_count']);
    }

    public function testIdentityKeyIsStableButRowHashTracksDataChanges(): void
    {
        $first = [
            'academic_year' => '2024-25', 'univ_code' => 'U-1234', 'college_code' => 'C-1007',
            'admission_application_form_no' => 'FORM-1', 'prn' => 'PRN-1', 'student_id' => 'STU-1',
            'program' => 'B.Sc.',
        ];
        $second = array_merge($first, ['program' => 'B.Sc. Honours']);

        self::assertSame(AdmissionSchema::recordKey($first), AdmissionSchema::recordKey($second));
        self::assertNotSame(AdmissionSchema::rowHash($first), AdmissionSchema::rowHash($second));

        $blankIdentity = array_merge($first, ['admission_application_form_no' => null, 'prn' => '-']);
        $naIdentity = array_merge($first, ['admission_application_form_no' => 'NA', 'prn' => 'NA']);
        self::assertSame(AdmissionSchema::recordKey($blankIdentity), AdmissionSchema::recordKey($naIdentity));
    }

    public function testTreatsDashPlaceholdersAsBlankOptionalValues(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], AdmissionSchema::HEADERS));
        $map = AdmissionSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'academic_year' => '2021-22',
            'univ_code' => 'U-1234',
            'college_code' => 'C-1007',
            'student_id' => 'STU-1',
            'admission_date' => '-',
            'eligibility_date' => '-',
            'receipt_amount' => '-',
            'pending_documents_count' => '-',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = AdmissionSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('NA', $result['data']['admission_date']);
        self::assertSame('NA', $result['data']['eligibility_date']);
        self::assertSame('NA', $result['data']['receipt_amount']);
        self::assertSame('NA', $result['data']['pending_documents_count']);
        self::assertSame('NA', $result['data']['religion']);
    }

    public function testApiSerializationUsesAllExpectedExternalFields(): void
    {
        $result = AdmissionSchema::toApi([
            'academic_year' => '2024-25',
            'univ_code' => 'U-1234',
            'admission_date' => '2026-09-16',
            'pending_documents_count' => null,
            'abc_id' => '391925116676',
            'id' => 154,
        ]);

        self::assertCount(45, $result);
        self::assertSame('391925116676', $result['APAAR ID (ABC ID)']);
        self::assertSame('16-09-2026', $result['Admission_Date']);
        self::assertSame('NA', $result['Pending_documents_count']);
        self::assertArrayNotHasKey('clientid', $result);
        self::assertArrayHasKey('Program_Part', $result);
        self::assertArrayHasKey('Student_City/Village', $result);
    }
}
