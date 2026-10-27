<?php

namespace App\Libraries;

final class ExpenseSchema
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'academic_year' => ['Financial year', 'Financial Year', 'Financial_Year', 'Academic Year', 'Academic_Year'],
        'institution_aishe_code' => ['AISHE Code', 'AISHE_Code', 'University / College AISHE code'],
        'institution_name' => ['University Name / College Name', 'University_College_Name'],
        'institution_type_name' => ['UniversityType / CollegeType Name', 'UniversityType_CollegeType_Name'],
        'salary_expense' => ['Salary Expense (in Rs.)', 'Salary Expense', 'Salary_Expense'],
        'non_salary_expense' => ['Non-Salary Expense (in Rs.)', 'Non Salary Expense (in Rs.)', 'Non_Salary_Expense'],
        'university_aishe_code' => ['University AISHE Code', 'University_AISHE_Code'],
        'university_name' => ['University Name', 'University_Name'],
    ];

    public const API_FIELDS = [
        'academic_year' => 'Financial year',
        'institution_aishe_code' => 'AISHE Code',
        'institution_name' => 'University Name / College Name',
        'institution_type_name' => 'UniversityType / CollegeType Name',
        'salary_expense' => 'Salary Expense (in Rs.)',
        'non_salary_expense' => 'Non-Salary Expense (in Rs.)',
        'university_aishe_code' => 'University AISHE Code',
        'university_name' => 'University Name',
    ];

    /** @return array{map: array<string, int>, missing: list<string>, duplicates: list<string>} */
    public static function mapHeaders(array $headers): array
    {
        $accepted = [];
        foreach (self::HEADERS as $field => $aliases) {
            foreach ($aliases as $alias) {
                $accepted[self::headerToken($alias)] = $field;
            }
        }

        $map = [];
        $duplicates = [];
        foreach ($headers as $index => $header) {
            $token = self::headerToken((string) $header);
            if ($token === '' || ! isset($accepted[$token])) {
                continue;
            }
            $field = $accepted[$token];
            if (isset($map[$field])) {
                $duplicates[] = self::HEADERS[$field][0];
                continue;
            }
            $map[$field] = (int) $index;
        }

        $missing = [];
        foreach (self::HEADERS as $field => $aliases) {
            if (! isset($map[$field])) {
                $missing[] = $aliases[0];
            }
        }

        return ['map' => $map, 'missing' => $missing, 'duplicates' => array_values(array_unique($duplicates))];
    }

    public static function headerToken(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));

        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $value));
    }

    public static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{data: array<string, mixed>, errors: list<array{field: string, value: mixed, message: string}>} */
    public static function normalizeRow(array $row, array $map): array
    {
        $data = [];
        foreach (self::HEADERS as $field => $_aliases) {
            $data[$field] = self::cleanScalar($row[$map[$field]] ?? null);
        }

        $errors = [];
        foreach ([
            'academic_year' => 'Financial year',
            'university_aishe_code' => 'University AISHE Code',
            'institution_aishe_code' => 'AISHE Code',
        ] as $field => $label) {
            if ($data[$field] === 'NA') {
                $errors[] = ['field' => $label, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        foreach ([
            'salary_expense' => 'Salary Expense (in Rs.)',
            'non_salary_expense' => 'Non-Salary Expense (in Rs.)',
        ] as $field => $label) {
            if ($data[$field] === 'NA') {
                continue;
            }
            $raw = preg_replace('/[\s,]/u', '', str_ireplace(['₹', 'INR', 'Rs.'], '', (string) $data[$field]));
            if ($raw === null || ! is_numeric($raw) || (float) $raw < 0) {
                $errors[] = ['field' => $label, 'value' => $data[$field], 'message' => $label . ' must be a number greater than or equal to zero.'];
                continue;
            }
            $data[$field] = rtrim(rtrim(number_format((float) $raw, 2, '.', ''), '0'), '.');
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function recordKey(array $data): string
    {
        $parts = [];
        foreach ([
            'academic_year', 'university_aishe_code', 'institution_aishe_code',
        ] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $parts[] = self::isNotApplicable($value) ? '' : strtolower($value);
        }

        return hash('sha256', implode('|', $parts));
    }

    public static function rowHash(array $data): string
    {
        ksort($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function toApi(array $row): array
    {
        $result = [];
        foreach (self::API_FIELDS as $field => $externalName) {
            $value = $row[$field] ?? null;
            if (in_array($field, ['salary_expense', 'non_salary_expense'], true) && $value !== null && $value !== '' && $value !== 'NA') {
                $number = (float) $value;
                $value = floor($number) === $number ? (int) $number : $number;
            } elseif ($value === null || $value === '') {
                $value = 'NA';
            }
            $result[$externalName] = $value;
        }

        return $result;
    }

    private static function cleanScalar(mixed $value): mixed
    {
        if ($value === null) {
            return 'NA';
        }
        if (is_string($value)) {
            $value = trim(str_replace("\xC2\xA0", ' ', $value));
            return self::isNotApplicable($value) ? 'NA' : $value;
        }

        return $value;
    }

    private static function isNotApplicable(string $value): bool
    {
        return $value === '' || in_array(strtoupper($value), ['-', '–', '—', 'NA', 'N/A', 'NULL'], true);
    }
}

