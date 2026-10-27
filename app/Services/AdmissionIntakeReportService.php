<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AdmissionIntakeReportService
{
    private const INVALID_IDENTIFIERS = ['', '-', '–', '—', 'NA', 'N/A', 'NULL'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array{years: list<string>, institutions: list<array{key: string, code: string, name: string}>} */
    public function options(): array
    {
        $years = [];
        foreach (['admissions', 'intakes'] as $table) {
            foreach ($this->db->table($table)->select('academic_year')->distinct()->get()->getResultArray() as $row) {
                $year = trim((string) ($row['academic_year'] ?? ''));
                if ($this->isApplicable($year)) {
                    $years[$year] = true;
                }
            }
        }
        $years = array_keys($years);
        rsort($years, SORT_NATURAL);

        $institutions = [];
        $admissionInstitutions = $this->db->table('admissions')
            ->select('college_code, college_name')
            ->distinct()
            ->get()
            ->getResultArray();
        foreach ($admissionInstitutions as $row) {
            $this->appendInstitutionOption($institutions, (string) ($row['college_code'] ?? ''), (string) ($row['college_name'] ?? ''));
        }

        $intakeInstitutions = $this->db->table('intakes')
            ->select('institution_aishe_code, institution_name')
            ->distinct()
            ->get()
            ->getResultArray();
        foreach ($intakeInstitutions as $row) {
            $this->appendInstitutionOption($institutions, (string) ($row['institution_aishe_code'] ?? ''), (string) ($row['institution_name'] ?? ''));
        }

        uasort($institutions, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return ['years' => $years, 'institutions' => array_values($institutions)];
    }

    /**
     * @param array{academicYear?: string, institution?: string, search?: string, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function rows(array $filters): array
    {
        $academicYear = trim((string) ($filters['academicYear'] ?? ''));
        $admissionBuilder = $this->db->table('admissions')
            ->select(
                "academic_year, college_name, program_code, program AS program_name, COUNT(*) AS admission_count, "
                . "MAX(CASE WHEN UPPER(TRIM(college_code)) NOT IN ('', '-', 'NA', 'N/A', 'NULL') THEN college_code ELSE NULL END) AS institution_code",
                false,
            )
            ->groupBy(['academic_year', 'college_name', 'program_code', 'program']);
        $intakeBuilder = $this->db->table('intakes')
            ->select(
                "academic_year, institution_aishe_code AS institution_code, institution_name, program_code, program_name, "
                . "MAX(course_name) AS course_name, "
                . "MAX(CASE WHEN total_intake REGEXP '^[0-9]+$' THEN CAST(total_intake AS UNSIGNED) ELSE NULL END) AS total_intake",
                false,
            )
            ->groupBy(['academic_year', 'institution_aishe_code', 'institution_name', 'program_code', 'program_name']);

        if ($academicYear !== '') {
            $admissionBuilder->where('academic_year', $academicYear);
            $intakeBuilder->where('academic_year', $academicYear);
        }

        $rows = $this->combine($admissionBuilder->get()->getResultArray(), $intakeBuilder->get()->getResultArray());
        $institution = trim((string) ($filters['institution'] ?? ''));
        $search = self::normalizeText((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? 'all'));

        $rows = array_values(array_filter($rows, static function (array $row) use ($institution, $search, $status): bool {
            if ($institution !== '' && $row['institution_key'] !== $institution) {
                return false;
            }
            if ($status !== '' && $status !== 'all' && $row['match_status'] !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $haystack = self::normalizeText(implode(' ', [
                $row['institution_code'],
                $row['institution_name'],
                $row['program_code'],
                $row['program_name'],
                $row['intake_program_name'],
                $row['course_name'],
            ]));

            return str_contains($haystack, $search);
        }));

        usort($rows, static function (array $left, array $right): int {
            $yearComparison = strnatcasecmp((string) $right['academic_year'], (string) $left['academic_year']);
            if ($yearComparison !== 0) {
                return $yearComparison;
            }
            $institutionComparison = strnatcasecmp((string) $left['institution_name'], (string) $right['institution_name']);

            return $institutionComparison !== 0
                ? $institutionComparison
                : strnatcasecmp((string) $left['program_name'], (string) $right['program_name']);
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $admissionRows
     * @param list<array<string, mixed>> $intakeRows
     * @return list<array<string, mixed>>
     */
    public function combine(array $admissionRows, array $intakeRows): array
    {
        $admissions = $this->consolidateAdmissions($admissionRows);
        $intakes = $this->consolidateIntakes($intakeRows);
        $lookup = [];
        foreach ($intakes as $index => $intake) {
            if ($intake['program_match_key'] === '') {
                continue;
            }
            foreach ($this->institutionLookupKeys($intake) as $institutionKey) {
                $lookup[$intake['academic_year'] . '|' . $institutionKey . '|' . $intake['program_match_key']][] = $index;
            }
        }

        $usedIntakes = [];
        $result = [];
        foreach ($admissions as $admission) {
            $matchIndex = null;
            if ($admission['program_match_key'] !== '') {
                foreach ($this->institutionLookupKeys($admission) as $institutionKey) {
                    $candidateKey = $admission['academic_year'] . '|' . $institutionKey . '|' . $admission['program_match_key'];
                    foreach ($lookup[$candidateKey] ?? [] as $candidateIndex) {
                        if (! isset($usedIntakes[$candidateIndex])) {
                            $matchIndex = $candidateIndex;
                            break 2;
                        }
                    }
                }
            }

            $intake = $matchIndex === null ? null : $intakes[$matchIndex];
            if ($matchIndex !== null) {
                $usedIntakes[$matchIndex] = true;
            }
            $result[] = $this->reportRow($admission, $intake);
        }

        foreach ($intakes as $index => $intake) {
            if (! isset($usedIntakes[$index])) {
                $result[] = $this->reportRow(null, $intake);
            }
        }

        return $result;
    }

    public static function normalizeInstitution(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        $value = (string) preg_replace('/\s*\([A-Z][A-Z0-9.&\s-]{1,14}\)\s*$/u', '', $value);

        return self::normalizeText($value);
    }

    public static function normalizeProgramCode(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        if (in_array(strtoupper($value), self::INVALID_IDENTIFIERS, true)) {
            return '';
        }
        $value = strtoupper((string) preg_replace('/\s+/u', '', $value));
        if (preg_match('/^\d+$/', $value) === 1) {
            $value = ltrim($value, '0');

            return $value === '' ? '0' : $value;
        }

        return $value;
    }

    public static function normalizeText(string $value): string
    {
        $value = str_replace(["\xC2\xA0", '&'], [' ', ' and '], trim($value));
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @param array<string, array{key: string, code: string, name: string}> $institutions */
    private function appendInstitutionOption(array &$institutions, string $code, string $name): void
    {
        $key = self::normalizeInstitution($name);
        if ($key === '') {
            return;
        }
        $code = $this->isApplicable($code) ? trim($code) : 'NA';
        if (! isset($institutions[$key])) {
            $institutions[$key] = ['key' => $key, 'code' => $code, 'name' => trim($name)];
        } elseif ($institutions[$key]['code'] === 'NA' && $code !== 'NA') {
            $institutions[$key]['code'] = $code;
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function consolidateAdmissions(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $year = trim((string) ($row['academic_year'] ?? ''));
            $institutionName = trim((string) ($row['college_name'] ?? 'NA'));
            $institutionCode = $this->isApplicable((string) ($row['institution_code'] ?? '')) ? trim((string) $row['institution_code']) : 'NA';
            $programCode = $this->isApplicable((string) ($row['program_code'] ?? '')) ? trim((string) $row['program_code']) : 'NA';
            $programName = trim((string) ($row['program_name'] ?? 'NA'));
            $programMatchKey = self::normalizeProgramCode($programCode);
            $institutionKey = self::normalizeInstitution($institutionName);
            $programGroupKey = $programMatchKey !== '' ? 'code:' . $programMatchKey : 'missing:' . self::normalizeText($programName);
            $key = $year . '|' . ($institutionKey !== '' ? $institutionKey : strtolower($institutionCode)) . '|' . $programGroupKey;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'academic_year' => $year,
                    'institution_code' => $institutionCode,
                    'institution_name' => $institutionName,
                    'institution_key' => $institutionKey,
                    'program_code' => $programCode,
                    'program_name' => $programName,
                    'program_match_key' => $programMatchKey,
                    'admission_count' => 0,
                ];
            }
            if ($groups[$key]['institution_code'] === 'NA' && $institutionCode !== 'NA') {
                $groups[$key]['institution_code'] = $institutionCode;
            }
            if ($this->isApplicable($programCode) && strlen($programCode) > strlen((string) $groups[$key]['program_code'])) {
                $groups[$key]['program_code'] = $programCode;
            }
            $groups[$key]['admission_count'] += (int) ($row['admission_count'] ?? 0);
        }

        return array_values($groups);
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function consolidateIntakes(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $year = trim((string) ($row['academic_year'] ?? ''));
            $institutionCode = $this->isApplicable((string) ($row['institution_code'] ?? '')) ? trim((string) $row['institution_code']) : 'NA';
            $institutionName = trim((string) ($row['institution_name'] ?? 'NA'));
            $programCode = $this->isApplicable((string) ($row['program_code'] ?? '')) ? trim((string) $row['program_code']) : 'NA';
            $programName = trim((string) ($row['program_name'] ?? 'NA'));
            $institutionKey = self::normalizeInstitution($institutionName);
            $programMatchKey = self::normalizeProgramCode($programCode);
            $programGroupKey = $programMatchKey !== '' ? 'code:' . $programMatchKey : 'missing:' . self::normalizeText($programName);
            $key = $year . '|' . ($institutionKey !== '' ? $institutionKey : strtolower($institutionCode)) . '|' . $programGroupKey;
            $intakeTotal = isset($row['total_intake']) && $row['total_intake'] !== null ? (int) $row['total_intake'] : null;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'academic_year' => $year,
                    'institution_code' => $institutionCode,
                    'institution_name' => $institutionName,
                    'institution_key' => $institutionKey,
                    'program_code' => $programCode,
                    'program_name' => $programName,
                    'program_match_key' => $programMatchKey,
                    'course_name' => trim((string) ($row['course_name'] ?? 'NA')),
                    'total_intake' => $intakeTotal,
                ];
            } else {
                if ($this->isApplicable($programCode) && strlen($programCode) > strlen((string) $groups[$key]['program_code'])) {
                    $groups[$key]['program_code'] = $programCode;
                }
                if ($intakeTotal !== null && ($groups[$key]['total_intake'] === null || $intakeTotal > $groups[$key]['total_intake'])) {
                    $groups[$key]['total_intake'] = $intakeTotal;
                }
            }
        }

        return array_values($groups);
    }

    /** @param array<string, mixed> $row @return list<string> */
    private function institutionLookupKeys(array $row): array
    {
        $keys = [];
        $code = trim((string) ($row['institution_code'] ?? ''));
        if ($this->isApplicable($code)) {
            $keys[] = 'code:' . strtolower($code);
        }
        $name = (string) ($row['institution_key'] ?? self::normalizeInstitution((string) ($row['institution_name'] ?? '')));
        if ($name !== '') {
            $keys[] = 'name:' . $name;
        }

        return array_values(array_unique($keys));
    }

    /** @param array<string, mixed>|null $admission @param array<string, mixed>|null $intake @return array<string, mixed> */
    private function reportRow(?array $admission, ?array $intake): array
    {
        $admissionCount = (int) ($admission['admission_count'] ?? 0);
        $intakeTotal = $intake['total_intake'] ?? null;
        $intakeTotal = $intakeTotal === null ? null : (int) $intakeTotal;
        $admissionInstitutionCode = (string) ($admission['institution_code'] ?? '');
        $intakeInstitutionCode = (string) ($intake['institution_code'] ?? '');
        $institutionCode = $this->isApplicable($admissionInstitutionCode)
            ? $admissionInstitutionCode
            : ($this->isApplicable($intakeInstitutionCode) ? $intakeInstitutionCode : 'NA');
        $institutionName = (string) ($admission['institution_name'] ?? $intake['institution_name'] ?? 'NA');
        $institutionKey = self::normalizeInstitution($institutionName);
        $admissionProgramCode = (string) ($admission['program_code'] ?? '');
        $intakeProgramCode = (string) ($intake['program_code'] ?? '');
        $programCode = $this->isApplicable($admissionProgramCode)
            ? $admissionProgramCode
            : ($this->isApplicable($intakeProgramCode) ? $intakeProgramCode : 'NA');
        $availableSeats = $intakeTotal === null ? null : $intakeTotal - $admissionCount;
        $utilization = $intakeTotal !== null && $intakeTotal > 0 ? round(($admissionCount / $intakeTotal) * 100, 1) : null;

        return [
            'academic_year' => (string) ($admission['academic_year'] ?? $intake['academic_year'] ?? 'NA'),
            'institution_code' => $institutionCode,
            'institution_name' => $institutionName,
            'institution_key' => $institutionKey,
            'institution_group_key' => (string) ($admission['academic_year'] ?? $intake['academic_year'] ?? 'NA') . '|' . $institutionKey,
            'program_code' => $programCode,
            'program_name' => (string) ($admission['program_name'] ?? $intake['program_name'] ?? 'NA'),
            'intake_program_name' => (string) ($intake['program_name'] ?? 'NA'),
            'course_name' => (string) ($intake['course_name'] ?? 'NA'),
            'intake_total' => $intakeTotal,
            'admission_count' => $admissionCount,
            'available_seats' => $availableSeats,
            'utilization' => $utilization,
            'match_status' => $admission === null ? 'no_admissions' : ($intake === null ? 'missing_intake' : 'matched'),
        ];
    }

    private function isApplicable(string $value): bool
    {
        return ! in_array(strtoupper(trim($value)), self::INVALID_IDENTIFIERS, true);
    }
}
