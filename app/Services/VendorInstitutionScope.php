<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use InvalidArgumentException;

/** One institution rule always belongs to a specific university. */
final class VendorInstitutionScope
{
    public const FIELDS = [
        'admissions' => ['univ_code', 'college_code'],
        'examinations' => ['univ_aishe_code', 'college_study_center_code'],
        'results' => ['university_aishe_code', 'organization_aishe_code'],
        'intake' => ['university_aishe_code', 'institution_aishe_code'],
        'expenses' => ['university_aishe_code', 'institution_aishe_code'],
        'placements' => ['university_aishe_code', 'institution_aishe_code'],
    ];

    private string $mode;
    private array $rules = [];

    public function __construct(array $vendor)
    {
        $this->mode = $vendor['institution_access_mode'] ?? 'all';
        if ($this->mode === 'restricted') {
            try {
                $rules = json_decode($vendor['institution_access_rules'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                $this->rules = self::canonicalRules($rules);
            } catch (\Throwable) {
                $this->rules = [];
            }
        }
    }

    public static function code(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function canonicalRules(mixed $rules): array
    {
        if (! is_array($rules) || ! array_is_list($rules) || count($rules) > 200) {
            throw new InvalidArgumentException('Provide at most 200 university/institution rules.');
        }
        $unique = [];
        $wholeUniversities = [];
        foreach ($rules as $rule) {
            if (! is_array($rule) || ! is_string($rule['university_code'] ?? null) || (! is_string($rule['institution_code'] ?? '') && ($rule['institution_code'] ?? null) !== null)) {
                throw new InvalidArgumentException('Each rule requires a university code and an optional institution code.');
            }
            $university = self::code($rule['university_code']);
            $institution = self::code($rule['institution_code'] ?? '');
            foreach ([$university, $institution] as $code) {
                if ($code !== '' && (strlen($code) > 100 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/D', $code) !== 1 || in_array($code, ['NA', 'N/A', 'NULL', '-'], true))) {
                    throw new InvalidArgumentException('Codes must use letters, numbers, periods, underscores, slashes, or hyphens (maximum 100 characters).');
                }
            }
            if ($university === '') {
                throw new InvalidArgumentException('Every access rule requires a university code.');
            }
            if ($institution === '') {
                $wholeUniversities[$university] = true;
            }
            $unique[$university . '|' . $institution] = ['university_code' => $university, 'institution_code' => $institution === '' ? null : $institution];
        }
        foreach ($unique as $key => $rule) {
            if ($rule['institution_code'] !== null && isset($wholeUniversities[$rule['university_code']])) {
                unset($unique[$key]);
            }
        }
        ksort($unique);
        return array_values($unique);
    }

    public function key(): string
    {
        return $this->mode === 'all' ? '' : hash('sha256', json_encode([$this->mode, $this->rules]));
    }

    public function idempotencyPayload(array $payload): array
    {
        // The fingerprint must always come from authenticated permissions, never JSON input.
        unset($payload['__institution_scope_key']);
        if ($this->key() !== '') {
            $payload['__institution_scope_key'] = $this->key();
        }
        return $payload;
    }

    public function allowsUniversity(string $university): bool
    {
        if ($this->mode === 'all') {
            return true;
        }
        foreach ($this->rules as $rule) {
            if ($rule['university_code'] === self::code($university)) {
                return true;
            }
        }
        return false;
    }

    public function allows(string $category, array $row): bool
    {
        if ($this->mode === 'all') {
            return true;
        }
        [$universityField, $institutionField] = self::FIELDS[$category] ?? throw new InvalidArgumentException('Unknown data category.');
        $university = self::code((string) ($row[$universityField] ?? ''));
        $institution = self::code((string) ($row[$institutionField] ?? ''));
        foreach ($this->rules as $rule) {
            if ($rule['university_code'] === $university && ($rule['institution_code'] === null || $rule['institution_code'] === $institution)) {
                return true;
            }
        }
        return false;
    }

    public function apply(BaseBuilder $builder, string $category): BaseBuilder
    {
        if ($this->mode === 'all') {
            return $builder;
        }
        if ($this->rules === []) {
            return $builder->where('1 = 0', null, false);
        }
        [$universityField, $institutionField] = self::FIELDS[$category] ?? throw new InvalidArgumentException('Unknown data category.');
        $builder->groupStart();
        foreach ($this->rules as $index => $rule) {
            $index === 0 ? $builder->groupStart() : $builder->orGroupStart();
            $builder->where('UPPER(TRIM(' . $universityField . '))', $rule['university_code']);
            if ($rule['institution_code'] !== null) {
                $builder->where('UPPER(TRIM(' . $institutionField . '))', $rule['institution_code']);
            }
            $builder->groupEnd();
        }
        return $builder->groupEnd();
    }
}
