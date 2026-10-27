<?php

namespace App\Libraries;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class AdmissionSchema
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'academic_year' => ['Academic_Year'],
        'univ_code' => ['Univ_Code', 'University_AISHE_Code'],
        'univ_name' => ['Univ_Name'],
        'college_code' => ['College_Code'],
        'college_name' => ['College_Name'],
        'college_state' => ['College_State'],
        'college_district' => ['College_District'],
        'college_taluka' => ['College_Taluka'],
        'college_city_village' => ['College_City/Village'],
        'college_pincode' => ['College_Pincode'],
        'admission_application_form_no' => ['Admission/Application_Form_No'],
        'admission_date' => ['Admission_Date'],
        'gender' => ['Gender'],
        'religion' => ['Religion'],
        'category' => ['Category'],
        'caste' => ['Caste'],
        'admitted_category' => ['Admitted_Category'],
        'student_location_category' => ['Student_Location_Category'],
        'student_country' => ['Student_Country'],
        'student_state' => ['Student_State'],
        'student_district' => ['Student_District'],
        'student_taluka' => ['Student_Taluka'],
        'student_city_village' => ['Stident_City/Village', 'Student_City/Village'],
        'student_location_pincode' => ['Student_location_PinCode'],
        'prn' => ['PRN'],
        'student_id' => ['StudentID'],
        'receipt_id' => ['pk_Receipt_ID'],
        'receipt_no' => ['Receipt_No'],
        'receipt_amount' => ['Receipt_Amount'],
        'physical_disability' => ['Physical_Disability'],
        'marital_status' => ['Marital_Status'],
        'medium_of_appearance' => ['Medium_of_Appearance'],
        'medium_of_instructions' => ['Medium_of_Instructions'],
        'mode_of_learning' => ['Mode_of_learning'],
        'program_code' => ['Program_code'],
        'program' => ['Program'],
        'specialization' => ['Specialization'],
        'program_type' => ['Program_type'],
        'program_part' => ['Program_Part'],
        'program_term' => ['Program_Term'],
        'eligibility_status' => ['Eligibility_Status'],
        'eligibility_date' => ['Eligibility_Date'],
        'all_documents_submitted' => ['All_documents_submitted'],
        'pending_documents_count' => ['Pending_documents_count'],
        'abc_id' => ['ABC ID', 'APAAR ID (ABC ID)', 'APAAR_ID'],
    ];

    public const API_FIELDS = [
        'abc_id' => 'APAAR ID (ABC ID)',
        'academic_year' => 'Academic_Year',
        'admission_application_form_no' => 'Admission/Application_Form_No',
        'admission_date' => 'Admission_Date',
        'admitted_category' => 'Admitted_Category',
        'all_documents_submitted' => 'All_documents_submitted',
        'caste' => 'Caste',
        'category' => 'Category',
        'college_city_village' => 'College_City/Village',
        'college_code' => 'College_Code',
        'college_district' => 'College_District',
        'college_name' => 'College_Name',
        'college_pincode' => 'College_Pincode',
        'college_state' => 'College_State',
        'college_taluka' => 'College_Taluka',
        'eligibility_date' => 'Eligibility_Date',
        'eligibility_status' => 'Eligibility_Status',
        'gender' => 'Gender',
        'marital_status' => 'Marital_Status',
        'medium_of_appearance' => 'Medium_of_Appearance',
        'medium_of_instructions' => 'Medium_of_Instructions',
        'mode_of_learning' => 'Mode_of_learning',
        'prn' => 'PRN',
        'pending_documents_count' => 'Pending_documents_count',
        'physical_disability' => 'Physical_Disability',
        'program' => 'Program',
        'program_part' => 'Program_Part',
        'program_term' => 'Program_Term',
        'program_code' => 'Program_code',
        'program_type' => 'Program_type',
        'receipt_amount' => 'Receipt_Amount',
        'receipt_no' => 'Receipt_No',
        'religion' => 'Religion',
        'specialization' => 'Specialization',
        'student_id' => 'StudentID',
        'student_city_village' => 'Student_City/Village',
        'student_country' => 'Student_Country',
        'student_district' => 'Student_District',
        'student_location_category' => 'Student_Location_Category',
        'student_state' => 'Student_State',
        'student_taluka' => 'Student_Taluka',
        'student_location_pincode' => 'Student_location_PinCode',
        'univ_code' => 'Univ_Code',
        'univ_name' => 'Univ_Name',
        'receipt_id' => 'pk_Receipt_ID',
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
            $value = $row[$map[$field]] ?? null;
            $data[$field] = self::cleanScalar($value);
        }

        $errors = [];
        foreach (['academic_year' => 'Academic_Year', 'univ_code' => 'Univ_Code', 'college_code' => 'College_Code'] as $field => $label) {
            if ($data[$field] === null) {
                $errors[] = ['field' => $label, 'value' => null, 'message' => $label . ' is required.'];
            }
        }

        if (self::allMissing($data, ['admission_application_form_no', 'prn', 'student_id'])) {
            $errors[] = [
                'field' => 'Admission/Application_Form_No',
                'value' => null,
                'message' => 'At least one of Admission/Application_Form_No, PRN, or StudentID is required.',
            ];
        }

        foreach (['admission_date' => 'Admission_Date', 'eligibility_date' => 'Eligibility_Date'] as $field => $label) {
            [$date, $dateError] = self::normalizeDate($data[$field]);
            $data[$field] = $date;
            if ($dateError !== null) {
                $errors[] = ['field' => $label, 'value' => $row[$map[$field]] ?? null, 'message' => $dateError];
            }
        }

        if ($data['receipt_amount'] !== 'NA') {
            $amount = str_replace([',', ' '], '', (string) $data['receipt_amount']);
            if (! is_numeric($amount)) {
                $errors[] = ['field' => 'Receipt_Amount', 'value' => $data['receipt_amount'], 'message' => 'Receipt_Amount must be numeric.'];
            } else {
                $data['receipt_amount'] = number_format((float) $amount, 2, '.', '');
            }
        }

        if ($data['pending_documents_count'] !== 'NA' && (filter_var($data['pending_documents_count'], FILTER_VALIDATE_INT) === false || (int) $data['pending_documents_count'] < 0)) {
            $errors[] = [
                'field' => 'Pending_documents_count',
                'value' => $data['pending_documents_count'],
                'message' => 'Pending_documents_count must be a whole number greater than or equal to zero.',
            ];
        } elseif ($data['pending_documents_count'] !== 'NA') {
            $data['pending_documents_count'] = (int) $data['pending_documents_count'];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function recordKey(array $data): string
    {
        $parts = [];
        foreach (['academic_year', 'univ_code', 'college_code', 'admission_application_form_no', 'prn', 'student_id'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if (in_array(strtoupper($value), ['NA', 'N/A', 'NULL', '-', '–', '—'], true)) {
                $value = '';
            }
            $parts[] = strtolower($value);
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
            if (in_array($field, ['admission_date', 'eligibility_date'], true) && $value !== 'NA') {
                $value = (new DateTimeImmutable((string) $value))->format('d-m-Y');
            }
            if ($field === 'pending_documents_count') {
                $value = $value === 'NA' ? 'NA' : (int) $value;
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

            if ($value === '' || in_array(strtoupper($value), ['-', '–', '—', 'NA', 'N/A', 'NULL'], true)) {
                return 'NA';
            }

            return $value;
        }

        return $value;
    }

    private static function allMissing(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = strtoupper(trim((string) ($data[$field] ?? '')));
            if ($value !== '' && $value !== 'NA' && $value !== 'N/A') {
                return false;
            }
        }

        return true;
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function normalizeDate(mixed $value): array
    {
        if ($value === null || $value === '' || strtoupper(trim((string) $value)) === 'NA') {
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
        if (in_array(strtoupper($text), ['NA', 'N/A', 'NULL', '00-00-0000', '0000-00-00'], true)) {
            return ['NA', null];
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'M j, Y', 'd-M-Y', 'j-M-Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
            $issues = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return [$date->format('Y-m-d'), null];
            }
        }

        return [null, 'Use an Excel date or one of these formats: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY, or 15-Oct-2021.'];
    }
}
