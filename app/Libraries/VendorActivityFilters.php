<?php

namespace App\Libraries;

use App\Services\VendorApiKeyService;
use DateTimeImmutable;
use InvalidArgumentException;

final class VendorActivityFilters
{
    public static function normalize(array $input, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $defaults = ['vendor_id' => '', 'category' => '', 'date_from' => $today->modify('-29 days')->format('Y-m-d'), 'date_to' => $today->format('Y-m-d'), 'page' => 1];
        $values = [];
        foreach ($defaults as $field => $default) {
            $value = $input[$field] ?? $default;
            if (! is_string($value) && ! is_int($value)) {
                throw new InvalidArgumentException('Select valid activity filters.');
            }
            $values[$field] = trim((string) $value);
        }
        if ($values['vendor_id'] !== '' && (filter_var($values['vendor_id'], FILTER_VALIDATE_INT) === false || (int) $values['vendor_id'] < 1)) {
            throw new InvalidArgumentException('Select a valid vendor.');
        }
        if ($values['category'] !== '' && ! isset(VendorApiKeyService::CATEGORIES[$values['category']])) {
            throw new InvalidArgumentException('Select a valid category.');
        }
        $dates = [];
        foreach (['date_from', 'date_to'] as $field) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values[$field]);
            if ($date === false || $date->format('Y-m-d') !== $values[$field]) {
                throw new InvalidArgumentException('Use valid dates in YYYY-MM-DD format.');
            }
            $dates[$field] = $date;
        }
        if ($dates['date_from'] > $dates['date_to'] || (int) $dates['date_from']->diff($dates['date_to'])->days > 365) {
            throw new InvalidArgumentException('Select an ordered date range of up to 366 days.');
        }
        $values['vendor_id'] = $values['vendor_id'] === '' ? null : (int) $values['vendor_id'];
        $values['page'] = max(1, min(1000000, (int) $values['page']));
        return $values;
    }
}
