<?php

namespace App\Database\Migrations;

use App\Libraries\ExaminationSchema;
use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddPaperNameToExaminationRecordKey extends Migration
{
    private const BATCH_SIZE = 500;

    private const LEGACY_KEY_FIELDS = [
        'academic_year',
        'univ_aishe_code',
        'exam_year',
        'exam_session',
        'course_code',
        'course_term',
        'branch',
        'admission_form_no',
        'prn',
        'exam_form_number',
        'seat_number',
        'paper_code',
        'assessment_type',
    ];

    private const CURRENT_KEY_FIELDS = [
        'academic_year',
        'univ_aishe_code',
        'exam_year',
        'exam_session',
        'course_code',
        'course_term',
        'branch',
        'admission_form_no',
        'prn',
        'exam_form_number',
        'seat_number',
        'paper_code',
        'paper_name',
        'assessment_type',
    ];

    public function up(): void
    {
        $this->rewriteKeys(static fn (array $row): string => ExaminationSchema::recordKey($row));
    }

    public function down(): void
    {
        $this->assertLegacyKeysAreUnique();
        $this->rewriteKeys(fn (array $row): string => $this->legacyRecordKey($row));
    }

    /** @param callable(array<string, mixed>): string $keyFactory */
    private function rewriteKeys(callable $keyFactory): void
    {
        $lastId = 0;

        while (true) {
            $rows = $this->db->table('examinations')
                ->select(implode(', ', array_merge(['id'], self::CURRENT_KEY_FIELDS)))
                ->where('id >', $lastId)
                ->orderBy('id', 'ASC')
                ->limit(self::BATCH_SIZE)
                ->get()
                ->getResultArray();

            if ($rows === []) {
                return;
            }

            $updates = [];
            foreach ($rows as $row) {
                $updates[] = [
                    'id' => (int) $row['id'],
                    'record_key' => $keyFactory($row),
                ];
                $lastId = (int) $row['id'];
            }

            $this->db->table('examinations')->updateBatch($updates, 'id', self::BATCH_SIZE);
        }
    }

    private function assertLegacyKeysAreUnique(): void
    {
        $lastId = 0;
        $seen = [];

        while (true) {
            $rows = $this->db->table('examinations')
                ->select(implode(', ', array_merge(['id'], self::LEGACY_KEY_FIELDS)))
                ->where('id >', $lastId)
                ->orderBy('id', 'ASC')
                ->limit(self::BATCH_SIZE)
                ->get()
                ->getResultArray();

            if ($rows === []) {
                return;
            }

            foreach ($rows as $row) {
                $key = $this->legacyRecordKey($row);
                if (isset($seen[$key])) {
                    throw new RuntimeException(
                        'Cannot roll back the Examination paper-name identity change because records '
                        . $seen[$key] . ' and ' . $row['id'] . ' share the legacy key.',
                    );
                }

                $seen[$key] = (int) $row['id'];
                $lastId = (int) $row['id'];
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function legacyRecordKey(array $row): string
    {
        $parts = [];
        foreach (self::LEGACY_KEY_FIELDS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            $parts[] = $this->isNotApplicable($value) ? '' : strtolower($value);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function isNotApplicable(string $value): bool
    {
        return $value === '' || in_array(strtoupper($value), ['-', '–', '—', 'NA', 'N/A', 'NULL'], true);
    }
}
