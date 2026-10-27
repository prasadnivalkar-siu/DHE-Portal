<?php

namespace App\Libraries;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class ExaminationSchema
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'univ_aishe_code' => ['UNIV_AISHE_CODE', 'Univ_AISHE_Code'],
        'univ_name' => ['UNIV_NAME', 'Univ_Name'],
        'academic_year' => ['ACADEMIC_YEAR', 'Academic_Year'],
        'exam_year' => ['EXAM_YEAR', 'Exam_Year'],
        'exam_session' => ['SESSION', 'session'],
        'course_abbr' => ['COURSEABBR', 'courseabbr'],
        'course_name' => ['COURSENAME', 'coursename'],
        'course_code' => ['COURSE_CODE', 'course_code'],
        'course_term' => ['COURSETERM', 'courseterm'],
        'course_pattern' => ['COURSEPATTERN', 'coursepattern'],
        'branch' => ['BRANCH', 'branch'],
        'admission_form_no' => ['ADMISSION_FORM_NO', 'admission_form_no'],
        'prn' => ['PRN', 'prn'],
        'gender' => ['GENDER', 'gender'],
        'medium_of_appearance' => ['MEDIUM_OF_APPEARANCE', 'medium_of_appearance'],
        'marital_status' => ['MARITAL_STATUS', 'marital_status'],
        'category' => ['CATEGORY', 'category'],
        'student_state' => ['STUDENT_STATE', 'student_state'],
        'student_district' => ['STUDENT_DISTRICT', 'student_district'],
        'taluka' => ['TALUKA', 'taluka'],
        'college_study_center_code' => ['COLLEGE/STUDY_CENTER_CODE', 'college/study_center_code'],
        'college_study_center_name' => ['COLLEGE/STUDY_CENTER_NAME', 'college/study_center_name'],
        'college_district' => ['COLLEGE_DISTRICT', 'college_district'],
        'college_taluka' => ['COLLEGE_TALUKA', 'college_taluka'],
        'paper_code' => ['PAPER_CODE', 'paper_code'],
        'paper_name' => ['PAPER_NAME', 'paper_name'],
        'teaching_learning_method' => ['TEACHING_LEARNING_METHOD', 'teaching_learning_method'],
        'assessment_method' => ['ASSESSMENT_METHOD', 'assessment_method'],
        'assessment_type' => ['ASSESSMENT_TYPE', 'assessment_type'],
        'exam_form_number' => ['EXAM_FORM_NUMBER', 'exam_form_number'],
        'exam_appearance_type' => ['EXAM_APPEARANCE_TYPE', 'exam_appearance_type'],
        'seat_number' => ['SEAT_NUMBER', 'seat_number'],
        'center_code' => ['CENTER_CODE', 'center_code'],
        'center_name' => ['CENTER_NAME', 'center_name'],
        'venue_code' => ['VENUE_CODE', 'venue_code'],
        'venue_name' => ['VENUE_NAME', 'venue_name'],
        'preferred_center_code' => ['PREFERRED_CENTER_CODE', 'preferred_center_code'],
        'preferred_center_name' => ['PREFERRED_CENTER_NAME', 'preferred_center_name'],
        'preferred_venue_code' => ['PREFERRED_VENUE_CODE', 'preferred_venue_code'],
        'preferred_venue_name' => ['PREFERRED_VENUE_NAME', 'preferred_venue_name'],
        'eligibility_status' => ['ELIGIBILITY_STATUS', 'eligibility_status'],
        'inward_date' => ['INWARD_DATE', 'inward_date'],
        'inward_time' => ['INWARD_TIME', 'inward_time'],
        'last_update_date' => ['LAST_UPDATE_DATE', 'last_update_date'],
    ];

    public const API_FIELDS = [
        'univ_aishe_code' => 'UNIV_AISHE_CODE',
        'univ_name' => 'UNIV_NAME',
        'academic_year' => 'ACADEMIC_YEAR',
        'exam_year' => 'EXAM_YEAR',
        'exam_session' => 'SESSION',
        'course_abbr' => 'COURSEABBR',
        'course_name' => 'COURSENAME',
        'course_code' => 'COURSE_CODE',
        'course_term' => 'COURSETERM',
        'course_pattern' => 'COURSEPATTERN',
        'branch' => 'BRANCH',
        'admission_form_no' => 'ADMISSION_FORM_NO',
        'prn' => 'PRN',
        'gender' => 'GENDER',
        'medium_of_appearance' => 'MEDIUM_OF_APPEARANCE',
        'marital_status' => 'MARITAL_STATUS',
        'category' => 'CATEGORY',
        'student_state' => 'STUDENT_STATE',
        'student_district' => 'STUDENT_DISTRICT',
        'taluka' => 'TALUKA',
        'college_study_center_code' => 'COLLEGE/STUDY_CENTER_CODE',
        'college_study_center_name' => 'COLLEGE/STUDY_CENTER_NAME',
        'college_district' => 'COLLEGE_DISTRICT',
        'college_taluka' => 'COLLEGE_TALUKA',
        'paper_code' => 'PAPER_CODE',
        'paper_name' => 'PAPER_NAME',
        'teaching_learning_method' => 'TEACHING_LEARNING_METHOD',
        'assessment_method' => 'ASSESSMENT_METHOD',
        'assessment_type' => 'ASSESSMENT_TYPE',
        'exam_form_number' => 'EXAM_FORM_NUMBER',
        'exam_appearance_type' => 'EXAM_APPEARANCE_TYPE',
        'seat_number' => 'SEAT_NUMBER',
        'center_code' => 'CENTER_CODE',
        'center_name' => 'CENTER_NAME',
        'venue_code' => 'VENUE_CODE',
        'venue_name' => 'VENUE_NAME',
        'preferred_center_code' => 'PREFERRED_CENTER_CODE',
        'preferred_center_name' => 'PREFERRED_CENTER_NAME',
        'preferred_venue_code' => 'PREFERRED_VENUE_CODE',
        'preferred_venue_name' => 'PREFERRED_VENUE_NAME',
        'eligibility_status' => 'ELIGIBILITY_STATUS',
        'inward_date' => 'INWARD_DATE',
        'inward_time' => 'INWARD_TIME',
        'last_update_date' => 'LAST_UPDATE_DATE',
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
        foreach (['univ_aishe_code' => 'UNIV_AISHE_CODE', 'academic_year' => 'ACADEMIC_YEAR'] as $field => $label) {
            if ($data[$field] === 'NA') {
                $errors[] = ['field' => $label, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        if (self::allMissing($data, ['admission_form_no', 'prn', 'exam_form_number', 'seat_number'])) {
            $errors[] = [
                'field' => 'PRN',
                'value' => null,
                'message' => 'At least one of ADMISSION_FORM_NO, PRN, EXAM_FORM_NUMBER, or SEAT_NUMBER is required.',
            ];
        }

        [$data['inward_date'], $dateError] = self::normalizeDate($data['inward_date']);
        if ($dateError !== null) {
            $errors[] = ['field' => 'INWARD_DATE', 'value' => $row[$map['inward_date']] ?? null, 'message' => $dateError];
        }
        [$data['inward_time'], $timeError] = self::normalizeTime($data['inward_time']);
        if ($timeError !== null) {
            $errors[] = ['field' => 'INWARD_TIME', 'value' => $row[$map['inward_time']] ?? null, 'message' => $timeError];
        }
        [$data['last_update_date'], $dateTimeError] = self::normalizeDateTime($data['last_update_date']);
        if ($dateTimeError !== null) {
            $errors[] = ['field' => 'LAST_UPDATE_DATE', 'value' => $row[$map['last_update_date']] ?? null, 'message' => $dateTimeError];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function recordKey(array $data): string
    {
        $parts = [];
        foreach ([
            'academic_year', 'univ_aishe_code', 'exam_year', 'exam_session', 'course_code',
            'course_term', 'branch', 'admission_form_no', 'prn', 'exam_form_number',
            'seat_number', 'paper_code', 'paper_name', 'assessment_type',
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
            if ($value === null || $value === '') {
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

    private static function allMissing(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! self::isNotApplicable(trim((string) ($data[$field] ?? '')))) {
                return false;
            }
        }

        return true;
    }

    /** @return array{0: string|null, 1: string|null} */
    private static function normalizeDate(mixed $value): array
    {
        if (self::isNotApplicable(trim((string) $value))) {
            return ['NA', null];
        }
        if (is_numeric($value) && (float) $value > 0) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d'), null];
            } catch (\Throwable) {
                return [null, 'The date value is not valid.'];
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

        return [null, 'Use an Excel date or a recognized date format.'];
    }

    /** @return array{0: string|null, 1: string|null} */
    private static function normalizeTime(mixed $value): array
    {
        if (self::isNotApplicable(trim((string) $value))) {
            return ['NA', null];
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $seconds = (int) round((float) $value * 86400) % 86400;
            return [gmdate('H:i:s', $seconds), null];
        }
        $text = trim((string) $value);
        foreach (['H:i:s', 'H:i', 'h:i:s A', 'h:i A'] as $format) {
            $time = DateTimeImmutable::createFromFormat('!' . $format, $text);
            $issues = DateTimeImmutable::getLastErrors();
            if ($time !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return [$time->format('H:i:s'), null];
            }
        }

        return [null, 'Use an Excel time or HH:MM[:SS].'];
    }

    /** @return array{0: string|null, 1: string|null} */
    private static function normalizeDateTime(mixed $value): array
    {
        if (self::isNotApplicable(trim((string) $value))) {
            return ['NA', null];
        }
        if (is_numeric($value) && (float) $value > 0) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s'), null];
            } catch (\Throwable) {
                return [null, 'The date/time value is not valid.'];
            }
        }
        try {
            return [(new DateTimeImmutable(trim((string) $value)))->format('Y-m-d H:i:s'), null];
        } catch (\Throwable) {
            return [null, 'Use an Excel date/time or a recognized date/time format.'];
        }
    }
}
