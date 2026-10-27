<?php

namespace App\Libraries;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

final class ResultSchema
{
    public const EXPECTED_HEADER_COUNT = 440;

    private const REQUIRED_FIELDS = [
        'UNIV_AISHE_CODE' => 'UNIV_AISHE_CODE',
        'SESSION' => 'SESSION (Academic Year)',
        'YEAR' => 'YEAR (Exam Year)',
        'SEASON' => 'SEASON',
    ];

    private const DATE_FIELDS = ['DOR', 'DOI', 'DOV', 'DOP', 'DOQ', 'DOS'];
    private const ZERO_ALLOWED_DATE_FIELDS = ['DOI', 'DOV', 'DOP', 'DOQ', 'DOS'];

    /** @return list<string> */
    public static function headers(): array
    {
        static $headers = null;
        if (is_array($headers)) {
            return $headers;
        }

        $appPath = defined('APPPATH') ? (string) constant('APPPATH') : dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $path = $appPath . 'Config' . DIRECTORY_SEPARATOR . 'result_headers.json';
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = $contents === false ? null : json_decode($contents, true);
        if (! is_array($decoded) || count($decoded) !== self::EXPECTED_HEADER_COUNT) {
            throw new RuntimeException('The Result header schema is missing or invalid.');
        }

        $headers = array_values(array_map(static fn (mixed $header): string => (string) $header, $decoded));

        return $headers;
    }

    /** @return array{map: array<string, int>, missing: list<string>, duplicates: list<string>} */
    public static function mapHeaders(array $headers): array
    {
        $accepted = [];
        foreach (self::headers() as $header) {
            $accepted[self::headerToken($header)] = $header;
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
                $duplicates[] = $field;
                continue;
            }
            $map[$field] = (int) $index;
        }

        $missing = [];
        foreach (self::headers() as $header) {
            if (! isset($map[$header])) {
                $missing[] = $header;
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
        foreach (self::headers() as $header) {
            $data[$header] = self::cleanScalar($row[$map[$header]] ?? null);
        }
        $data['SEASON'] = self::normalizeSeason($data['SEASON'] ?? 'NA');

        $errors = [];
        foreach (self::REQUIRED_FIELDS as $field => $label) {
            if (($data[$field] ?? 'NA') === 'NA') {
                $errors[] = ['field' => $field, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        if (self::allMissing($data, ['PRN', 'REGN_NO', 'RROLL', 'ABC_ACCOUNT_ID'])) {
            $errors[] = [
                'field' => 'Student identifier',
                'value' => null,
                'message' => 'At least one of PRN, REGN_NO, RROLL, or ABC_ACCOUNT_ID is required.',
            ];
        }

        if (($data['YEAR'] ?? 'NA') !== 'NA') {
            $examYear = filter_var($data['YEAR'], FILTER_VALIDATE_INT);
            if ($examYear === false || $examYear < 1900 || $examYear > 2200) {
                $errors[] = ['field' => 'YEAR', 'value' => $data['YEAR'], 'message' => 'YEAR must be a four-digit exam year.'];
            } else {
                $data['YEAR'] = (string) $examYear;
            }
        }

        foreach (self::DATE_FIELDS as $field) {
            [$normalized, $error] = self::normalizeDate(
                $data[$field] ?? 'NA',
                in_array($field, self::ZERO_ALLOWED_DATE_FIELDS, true),
            );
            if ($error !== null) {
                $errors[] = ['field' => $field, 'value' => $data[$field] ?? null, 'message' => $field . ': ' . $error];
            } else {
                $data[$field] = $normalized;
            }
        }

        [$lastUpdate, $lastUpdateError] = self::normalizeDateTime($data['LAST_UPDATE_DATE'] ?? 'NA', true);
        if ($lastUpdateError !== null) {
            $errors[] = ['field' => 'LAST_UPDATE_DATE', 'value' => $data['LAST_UPDATE_DATE'] ?? null, 'message' => 'LAST_UPDATE_DATE: ' . $lastUpdateError];
        } else {
            $data['LAST_UPDATE_DATE'] = $lastUpdate;
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function recordKey(array $data): string
    {
        $parts = [];
        foreach ([
            'SESSION', 'UNIV_AISHE_CODE', 'ORG_AISHE_CODE', 'YEAR', 'SEASON',
            'PRN', 'REGN_NO', 'RROLL', 'COURSE_CODE', 'SEM', 'EXAM_TYPE', 'TERM_TYPE',
        ] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($field === 'SEASON') {
                $value = self::normalizeSeason($value);
            }
            $parts[] = self::isNotApplicable($value) ? '' : strtolower($value);
        }

        return hash('sha256', implode('|', $parts));
    }

    public static function rowHash(array $data): string
    {
        ksort($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public static function toDatabase(array $data): array
    {
        $data['SEASON'] = self::normalizeSeason($data['SEASON'] ?? 'NA');
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'academic_year' => self::text($data['SESSION'] ?? 'NA'),
            'exam_year' => self::text($data['YEAR'] ?? 'NA'),
            'season' => self::text($data['SEASON'] ?? 'NA'),
            'university_aishe_code' => self::text($data['UNIV_AISHE_CODE'] ?? 'NA'),
            'university_name' => self::text($data['UNIV_NAME'] ?? 'NA'),
            'organization_aishe_code' => self::text($data['ORG_AISHE_CODE'] ?? 'NA'),
            'organization_code' => self::text($data['ORG_CODE'] ?? 'NA'),
            'organization_name' => self::text($data['ORG_NAME'] ?? 'NA'),
            'course_code' => self::text($data['COURSE_CODE'] ?? 'NA'),
            'course_name' => self::text($data['COURSE_NAME'] ?? 'NA'),
            'prn' => self::text($data['PRN'] ?? 'NA'),
            'registration_no' => self::text($data['REGN_NO'] ?? 'NA'),
            'roll_no' => self::text($data['RROLL'] ?? 'NA'),
            'semester' => self::text($data['SEM'] ?? 'NA'),
            'result_status' => self::text($data['RESULT'] ?? 'NA'),
            'result_payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    public static function toApi(array $row): array
    {
        $payload = json_decode((string) ($row['result_payload'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $result = [];
        foreach (self::headers() as $header) {
            $value = $payload[$header] ?? 'NA';
            if ($header === 'SEASON') {
                $value = self::normalizeSeason($value);
            }
            $result[$header] = $value === null || $value === '' ? 'NA' : $value;
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

    private static function allMissing(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! self::isNotApplicable(trim((string) ($data[$field] ?? '')))) {
                return false;
            }
        }

        return true;
    }

    private static function text(mixed $value): string
    {
        $text = trim((string) $value);

        return self::isNotApplicable($text) ? 'NA' : $text;
    }

    public static function normalizeSeason(mixed $value): string
    {
        $season = self::text($value);
        if ($season === 'NA') {
            return $season;
        }

        $normalized = preg_replace('/\s+\d{4}(?:\s*-\s*\d{2,4})?\s*$/u', '', $season);

        return trim((string) $normalized);
    }

    public static function canonicalAcademicYear(mixed $value): string
    {
        $academicYear = trim((string) $value);
        if (preg_match('/^(\d{4})-\d{2,4}$/', $academicYear, $matches) !== 1) {
            return $academicYear;
        }

        $startYear = (int) $matches[1];

        return sprintf('%04d-%02d', $startYear, ($startYear + 1) % 100);
    }

    /** @return array{0: string, 1: string|null} */
    private static function normalizeDate(mixed $value, bool $allowZero = false): array
    {
        if (self::isNotApplicable(trim((string) $value))) {
            return ['NA', null];
        }
        if (is_numeric($value) && (float) $value === 0.0) {
            return $allowZero
                ? ['0', null]
                : ['NA', 'Use an Excel date or a recognized date format.'];
        }
        if (is_numeric($value) && (float) $value > 0) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d'), null];
            } catch (\Throwable) {
                return ['NA', 'The date value is not valid.'];
            }
        }

        $text = trim((string) $value);
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd-M-Y', 'j-M-Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
            $issues = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return [$date->format('Y-m-d'), null];
            }
        }

        return ['NA', 'Use an Excel date or a recognized date format.'];
    }

    /** @return array{0: string, 1: string|null} */
    private static function normalizeDateTime(mixed $value, bool $allowZero = false): array
    {
        if (self::isNotApplicable(trim((string) $value))) {
            return ['NA', null];
        }
        if (is_numeric($value) && (float) $value === 0.0) {
            return $allowZero
                ? ['0', null]
                : ['NA', 'Use an Excel date/time or a recognized date/time format.'];
        }
        if (is_numeric($value) && (float) $value > 0) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s'), null];
            } catch (\Throwable) {
                return ['NA', 'The date/time value is not valid.'];
            }
        }
        try {
            return [(new DateTimeImmutable(trim((string) $value)))->format('Y-m-d H:i:s'), null];
        } catch (\Throwable) {
            return ['NA', 'Use an Excel date/time or a recognized date/time format.'];
        }
    }
}
