<?php

namespace App\Libraries;

final class IntakeSchema
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'academic_year' => ['Academic Year', 'Academic_Year'],
        'university_aishe_code' => ['University AISHE Code', 'University_AISHE_Code'],
        'university_name' => ['University Name', 'University_Name'],
        'institution_aishe_code' => ['University / College AISHE code', 'University_College_AISHE_Code'],
        'institution_name' => ['University Name / College Name', 'University_College_Name'],
        'institution_type_name' => ['UniversityType / CollegeType Name', 'UniversityType_CollegeType_Name'],
        'program_code' => ['Programme Code', 'Program Code', 'Programme_Code', 'Program_Code'],
        'program_name' => ['Program Name', 'Program_Name'],
        'course_name' => ['Course Name', 'Course_Name'],
        'level' => ['Level'],
        'total_intake' => ['TotalIntake', 'Total Intake'],
        'designation' => ['Designation'],
        'faculty_permanent' => ['Faculty (Permanent)', 'Faculty_Permanent'],
        'faculty_temporary' => ['Faculty (Temporory)', 'Faculty (Temporary)', 'Faculty_Temporary'],
        'faculty_chb' => ['Faculty (CHB)', 'Faculty_CHB'],
    ];

    /** @var list<string> */
    private const OPTIONAL_FIELDS = ['program_code'];

    public const API_FIELDS = [
        'academic_year' => 'Academic Year',
        'university_aishe_code' => 'University AISHE Code',
        'university_name' => 'University Name',
        'institution_aishe_code' => 'University / College AISHE code',
        'institution_name' => 'University Name / College Name',
        'institution_type_name' => 'UniversityType / CollegeType Name',
        'program_code' => 'Programme Code',
        'program_name' => 'Program Name',
        'course_name' => 'Course Name',
        'level' => 'Level',
        'total_intake' => 'TotalIntake',
        'designation' => 'Designation',
        'faculty_permanent' => 'Faculty (Permanent)',
        'faculty_temporary' => 'Faculty (Temporory)',
        'faculty_chb' => 'Faculty (CHB)',
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
            if (! in_array($field, self::OPTIONAL_FIELDS, true) && ! isset($map[$field])) {
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
            $data[$field] = isset($map[$field])
                ? self::cleanScalar($row[$map[$field]] ?? null)
                : 'NA';
        }

        $errors = [];
        foreach ([
            'academic_year' => 'Academic Year',
            'university_aishe_code' => 'University AISHE Code',
            'institution_aishe_code' => 'University / College AISHE code',
        ] as $field => $label) {
            if ($data[$field] === 'NA') {
                $errors[] = ['field' => $label, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        foreach ([
            'total_intake' => 'TotalIntake',
            'faculty_permanent' => 'Faculty (Permanent)',
            'faculty_temporary' => 'Faculty (Temporory)',
            'faculty_chb' => 'Faculty (CHB)',
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
            'program_name', 'course_name', 'level', 'designation',
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
            if (in_array($field, ['total_intake', 'faculty_permanent', 'faculty_temporary', 'faculty_chb'], true) && $value !== null && $value !== '' && $value !== 'NA') {
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
