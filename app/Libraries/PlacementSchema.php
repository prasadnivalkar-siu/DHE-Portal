<?php

namespace App\Libraries;

final class PlacementSchema
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'academic_year' => ['Academic Year', 'Academic_Year'],
        'institution_aishe_code' => ['University / College AISHE code', 'University_College_AISHE_Code'],
        'institution_name' => ['University Name / College Name', 'University_College_Name'],
        'program_name' => ['ProgramName', 'Program Name', 'Program_Name'],
        'course_name' => ['CourseName', 'Course Name', 'Course_Name'],
        'institution_type_name' => ['UniversityType / CollegeType Name', 'UniversityType_CollegeType_Name'],
        'company_name' => ['Company Name', 'Company_Name'],
        'package_1_to_3_lakh' => ['Package range (1 to 3 Lakh)', 'Package_Range_1_To_3_Lakh'],
        'package_3_to_5_lakh' => ['Package range (3 to 5 Lakh)', 'Package_Range_3_To_5_Lakh'],
        'package_5_to_10_lakh' => ['Package range (5 to 10 Lakh)', 'Package_Range_5_To_10_Lakh'],
        'package_10_to_15_lakh' => ['Package range (10 to 15 Lakh)', 'Package_Range_10_To_15_Lakh'],
        'package_above_15_lakh' => ['Package range More than 15 Lakh', 'Package_Range_More_Than_15_Lakh'],
        'university_aishe_code' => ['University AISHE Code', 'University_AISHE_Code'],
        'university_name' => ['University Name', 'University_Name'],
    ];

    public const API_FIELDS = [
        'academic_year' => 'Academic Year',
        'institution_aishe_code' => 'University / College AISHE code',
        'institution_name' => 'University Name / College Name',
        'program_name' => 'ProgramName',
        'course_name' => 'CourseName',
        'institution_type_name' => 'UniversityType / CollegeType Name',
        'company_name' => 'Company Name',
        'package_1_to_3_lakh' => 'Package range (1 to 3 Lakh)',
        'package_3_to_5_lakh' => 'Package range (3 to 5 Lakh)',
        'package_5_to_10_lakh' => 'Package range (5 to 10 Lakh)',
        'package_10_to_15_lakh' => 'Package range (10 to 15 Lakh)',
        'package_above_15_lakh' => 'Package range More than 15 Lakh',
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
            'academic_year' => 'Academic Year',
            'university_aishe_code' => 'University AISHE Code',
            'institution_aishe_code' => 'University / College AISHE code',
            'company_name' => 'Company Name',
        ] as $field => $label) {
            if ($data[$field] === 'NA') {
                $errors[] = ['field' => $label, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        foreach ([
            'package_1_to_3_lakh' => 'Package range (1 to 3 Lakh)',
            'package_3_to_5_lakh' => 'Package range (3 to 5 Lakh)',
            'package_5_to_10_lakh' => 'Package range (5 to 10 Lakh)',
            'package_10_to_15_lakh' => 'Package range (10 to 15 Lakh)',
            'package_above_15_lakh' => 'Package range More than 15 Lakh',
        ] as $field => $label) {
            if ($data[$field] === 'NA') {
                continue;
            }
            $number = filter_var($data[$field], FILTER_VALIDATE_INT);
            if ($number === false || $number < 0) {
                $errors[] = ['field' => $label, 'value' => $data[$field], 'message' => $label . ' must be a whole number greater than or equal to zero.'];
                continue;
            }
            $data[$field] = (int) $number;
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function recordKey(array $data): string
    {
        $parts = [];
        foreach ([
            'academic_year', 'university_aishe_code', 'institution_aishe_code',
            'program_name', 'course_name', 'company_name',
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
            if (in_array($field, ['package_1_to_3_lakh', 'package_3_to_5_lakh', 'package_5_to_10_lakh', 'package_10_to_15_lakh', 'package_above_15_lakh'], true) && $value !== null && $value !== '' && $value !== 'NA') {
                $value = (int) $value;
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
