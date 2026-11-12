<?php

namespace App\Libraries;

use InvalidArgumentException;

/** Adapts named JSON fields to the same normalization and identity rules as Excel. */
final class VendorImportSchema
{
    public const CATEGORIES = [
        'admissions' => [AdmissionSchema::class, 'admission', 'admissions'],
        'examinations' => [ExaminationSchema::class, 'examination', 'examinations'],
        'results' => [ResultSchema::class, 'result', 'results'],
        'intake' => [IntakeSchema::class, 'intake', 'intakes'],
        'expenses' => [ExpenseSchema::class, 'expense', 'expenses'],
        'placements' => [PlacementSchema::class, 'placement', 'placements'],
    ];

    public static function definition(string $category): array
    {
        return self::CATEGORIES[$category] ?? throw new InvalidArgumentException('Unknown import category.');
    }

    public static function normalize(string $category, mixed $record): array
    {
        [$schema] = self::definition($category);
        $errors = [];
        if (! is_array($record) || $record === [] || array_is_list($record)) {
            return ['data' => [], 'errors' => [['field' => 'Records', 'message' => 'Each record must be a nonempty JSON object.']]];
        }
        $map = [];
        $values = [];
        static $aliases = [];
        if (! isset($aliases[$category])) {
            $aliases[$category] = [];
            if ($category === 'results') {
                foreach ($schema::headers() as $field) {
                    $aliases[$category][$schema::headerToken($field)] = $field;
                }
            } else {
                foreach ($schema::HEADERS as $field => $names) {
                    foreach (array_merge($names, [$schema::API_FIELDS[$field]]) as $name) {
                        $aliases[$category][$schema::headerToken($name)] = $field;
                    }
                }
            }
        }
        foreach ($record as $name => $value) {
            if (count($errors) >= 20) {
                break;
            }
            if (! is_string($name) || (! is_scalar($value) && $value !== null)) {
                $errors[] = ['field' => (string) $name, 'message' => 'Field values must be strings, numbers, booleans, or null.'];
                continue;
            }
            $field = $aliases[$category][$schema::headerToken($name)] ?? null;
            if ($field === null) {
                $errors[] = ['field' => $name, 'message' => 'Unknown data field. Use the category API response field names.'];
                continue;
            }
            if (isset($map[$field])) {
                $errors[] = ['field' => $name, 'message' => 'This field was supplied more than once using different aliases.'];
                continue;
            }
            if (is_string($value) && mb_strlen($value) > 10000) {
                $errors[] = ['field' => $name, 'message' => 'A field value may not exceed 10,000 characters.'];
                continue;
            }
            $map[$field] = count($values);
            $values[] = $value;
        }
        if ($errors !== []) {
            return ['data' => [], 'errors' => $errors];
        }
        // Normalize methods expect map entries even when a JSON field is omitted.
        $fields = $category === 'results' ? $schema::headers() : array_keys($schema::HEADERS);
        foreach ($fields as $field) {
            if (! isset($map[$field])) {
                $map[$field] = count($values);
                $values[] = null;
            }
        }
        $normalized = $schema::normalizeRow($values, $map);
        $data = $normalized['data'];
        // Older Admission normalization checks null rather than its NA placeholder.
        if ($category === 'admissions') {
            foreach (['academic_year', 'univ_code', 'college_code'] as $field) {
                if (($data[$field] ?? 'NA') === 'NA') {
                    $normalized['errors'][] = ['field' => $schema::API_FIELDS[$field], 'message' => 'This field is required.'];
                }
            }
        }
        if ($normalized['errors'] !== []) {
            return ['data' => [], 'errors' => $normalized['errors']];
        }
        $dbData = $category === 'results' ? $schema::toDatabase($data) : $data;
        $dbData['record_key'] = $schema::recordKey($data);
        $dbData['row_hash'] = $schema::rowHash($data);

        return ['data' => $dbData, 'errors' => []];
    }

    public static function requestHash(array $records): string
    {
        $sort = static function (mixed $value) use (&$sort): mixed {
            if (is_array($value)) {
                if (! array_is_list($value)) {
                    ksort($value);
                }
                return array_map($sort, $value);
            }
            return $value;
        };
        return hash('sha256', json_encode($sort($records), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
