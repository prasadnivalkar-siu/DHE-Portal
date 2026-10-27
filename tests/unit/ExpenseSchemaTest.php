<?php

namespace Tests\Unit;

use App\Libraries\ExpenseSchema;
use PHPUnit\Framework\TestCase;

final class ExpenseSchemaTest extends TestCase
{
    public function testMapsAllEightTemplateHeadersAndAliases(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExpenseSchema::HEADERS));
        $headers[array_search('Financial year', $headers, true)] = 'Financial_Year';
        $headers[array_search('AISHE Code', $headers, true)] = 'AISHE_Code';

        $result = ExpenseSchema::mapHeaders($headers);

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['duplicates']);
        self::assertCount(8, $result['map']);
        self::assertArrayHasKey('salary_expense', $result['map']);
        self::assertArrayHasKey('non_salary_expense', $result['map']);
    }

    public function testNormalizesBlankDashAndCurrencyAmounts(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExpenseSchema::HEADERS));
        $map = ExpenseSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        foreach ([
            'academic_year' => '2024-25  ',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'institution_name' => '-',
            'salary_expense' => '₹ 45,208,168',
            'non_salary_expense' => '5059082.50',
        ] as $field => $value) {
            $row[$map[$field]] = $value;
        }

        $result = ExpenseSchema::normalizeRow($row, $map);

        self::assertSame([], $result['errors']);
        self::assertSame('2024-25', $result['data']['academic_year']);
        self::assertSame('NA', $result['data']['institution_name']);
        self::assertSame('45208168', $result['data']['salary_expense']);
        self::assertSame('5059082.5', $result['data']['non_salary_expense']);
    }

    public function testRejectsMissingIdentifiersAndInvalidAmounts(): void
    {
        $headers = array_values(array_map(static fn (array $aliases): string => $aliases[0], ExpenseSchema::HEADERS));
        $map = ExpenseSchema::mapHeaders($headers)['map'];
        $row = array_fill(0, count($headers), null);
        $row[$map['academic_year']] = '2024-25';
        $row[$map['salary_expense']] = '-1';
        $row[$map['non_salary_expense']] = 'not a number';

        $result = ExpenseSchema::normalizeRow($row, $map);

        self::assertSame(
            ['University AISHE Code', 'AISHE Code', 'Salary Expense (in Rs.)', 'Non-Salary Expense (in Rs.)'],
            array_column($result['errors'], 'field'),
        );
    }

    public function testRecordIdentityIsStableWhileRowHashTracksAmountChanges(): void
    {
        $first = [
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'salary_expense' => '1000',
            'non_salary_expense' => '500',
        ];
        $second = array_merge($first, ['salary_expense' => '1200']);

        self::assertSame(ExpenseSchema::recordKey($first), ExpenseSchema::recordKey($second));
        self::assertNotSame(ExpenseSchema::rowHash($first), ExpenseSchema::rowHash($second));
    }

    public function testApiSerializationUsesTemplateNamesWithoutInternalId(): void
    {
        $result = ExpenseSchema::toApi([
            'id' => 51,
            'academic_year' => '2024-25',
            'university_aishe_code' => 'U-TEST',
            'institution_aishe_code' => 'C-100',
            'salary_expense' => '45208168',
            'non_salary_expense' => '5059082.5',
        ]);

        self::assertCount(8, $result);
        self::assertArrayNotHasKey('clientid', $result);
        self::assertSame('2024-25', $result['Financial year']);
        self::assertSame(45208168, $result['Salary Expense (in Rs.)']);
        self::assertSame(5059082.5, $result['Non-Salary Expense (in Rs.)']);
        self::assertSame('NA', $result['University Name / College Name']);
        self::assertArrayHasKey('AISHE Code', $result);
    }
}
