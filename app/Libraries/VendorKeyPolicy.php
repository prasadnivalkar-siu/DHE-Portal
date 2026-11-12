<?php

namespace App\Libraries;

use DateTimeImmutable;
use InvalidArgumentException;

final class VendorKeyPolicy
{
    public const DEFAULT_LIFETIME_DAYS = 90;
    public const DEFAULT_OVERLAP_MINUTES = 60;
    public const MAX_OVERLAP_MINUTES = 1440;

    /** Form times and database times use the application's configured timezone. */
    public static function expiration(mixed $input, ?DateTimeImmutable $now = null): ?string
    {
        if ($input === null || $input === '') { return null; }
        if (! is_string($input)) { throw new InvalidArgumentException('Enter a valid key expiry date and time.'); }
        $date = null;
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $candidate = DateTimeImmutable::createFromFormat('!' . $format, $input);
            if ($candidate !== false && $candidate->format($format) === $input) { $date = $candidate; break; }
        }
        if ($date === null || $date <= ($now ?? new DateTimeImmutable())) {
            throw new InvalidArgumentException('Key expiry must be a valid future date and time, or blank for no expiry.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    public static function usable(?string $expiresAt, ?DateTimeImmutable $now = null): bool
    {
        if ($expiresAt === null) { return true; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiresAt);
        return $date !== false && $date->format('Y-m-d H:i:s') === $expiresAt && $date > ($now ?? new DateTimeImmutable());
    }

    public static function overlapMinutes(mixed $input): int
    {
        if ((! is_int($input) && ! is_string($input)) || filter_var($input, FILTER_VALIDATE_INT) === false || (int) $input < 0 || (int) $input > self::MAX_OVERLAP_MINUTES) {
            throw new InvalidArgumentException('Overlap must be a whole number from 0 to 1,440 minutes.');
        }
        return (int) $input;
    }

    /** Retain just the immediately preceding hash; a new rotation discards any older key. */
    public static function rotation(array $vendor, array $credentials, ?string $expiresAt, int $overlapMinutes, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        self::overlapMinutes($overlapMinutes);
        if (! self::usable($expiresAt, $now)) { throw new InvalidArgumentException('The replacement key must expire in the future.'); }
        $data = [
            'api_key_hash' => $credentials['api_key_hash'], 'api_key_prefix' => $credentials['api_key_prefix'],
            'api_key_encrypted' => $credentials['api_key_encrypted'], 'key_generated_at' => $now->format('Y-m-d H:i:s'),
            'api_key_expires_at' => $expiresAt, 'current_key_last_used_at' => null,
            'previous_api_key_hash' => null, 'previous_api_key_prefix' => null,
            'previous_key_valid_until' => null, 'previous_key_last_used_at' => null,
        ];
        if ($overlapMinutes > 0 && self::usable($vendor['api_key_expires_at'] ?? null, $now)) {
            $cutoff = $now->modify('+' . $overlapMinutes . ' minutes')->format('Y-m-d H:i:s');
            foreach ([$vendor['api_key_expires_at'] ?? null, $expiresAt] as $deadline) {
                if ($deadline !== null && $deadline < $cutoff) { $cutoff = $deadline; }
            }
            $data['previous_api_key_hash'] = $vendor['api_key_hash'];
            $data['previous_api_key_prefix'] = $vendor['api_key_prefix'];
            $data['previous_key_valid_until'] = $cutoff;
            $data['previous_key_last_used_at'] = $vendor['current_key_last_used_at'] ?? null;
        }
        return $data;
    }
}
