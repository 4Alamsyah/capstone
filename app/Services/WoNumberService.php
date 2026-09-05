<?php

namespace App\Services;

class WoNumberService
{
    public static function generate(array $format, int $sequenceNumber): string
    {
        $parts = [];
        $prefix = $format['prefix'] ?? 'MO';

        foreach ($format['components'] as $component) {
            $value = self::formatComponent($component, $sequenceNumber, $prefix);
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            }
        }

        if (\count($parts) > 0) {
            return implode($format['separator'] ?? '-', $parts);
        }

        $sep = $format['separator'] ?? '-';
        return $prefix . $sep . now()->format('Ym') . $sep . str_pad((string) $sequenceNumber, 5, '0', STR_PAD_LEFT);
    }

    public static function preview(array $format): string
    {
        return self::generate($format, 1);
    }

    /**
     * Next sequence number for a stem, based on the highest sequence already used
     * (not a row count) - so a deleted/missing number in the middle of the sequence
     * never causes a freshly generated number to collide with one still on file.
     *
     * @param  iterable<string|null>  $existingValues  values already matching the stem+separator pattern
     */
    public static function nextSequenceNumber(string $stem, string $separator, iterable $existingValues): int
    {
        $prefix = $stem !== '' ? $stem.$separator : '';
        $prefixLength = mb_strlen($prefix);
        $max = 0;

        foreach ($existingValues as $value) {
            $value = (string) $value;

            if ($prefix !== '' && ! str_starts_with($value, $prefix)) {
                continue;
            }

            $suffix = mb_substr($value, $prefixLength);

            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max + 1;
    }

    public static function stem(array $format): string
    {
        $parts = [];
        $prefix = $format['prefix'] ?? 'MO';

        foreach ($format['components'] as $component) {
            if ($component['type'] === 'sequential') {
                continue;
            }

            $value = self::formatComponent($component, 0, $prefix);
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            }
        }

        return \count($parts) > 0 ? implode($format['separator'] ?? '-', $parts) : '';
    }

    private static function formatComponent(array $component, int $sequenceNumber, string $prefix): ?string
    {
        return match ($component['type']) {
            'prefix' => $prefix,
            'year' => self::formatYear($component['format']),
            'month' => self::formatMonth($component['format']),
            'sequential' => self::formatSequential($component['format'], $sequenceNumber),
            default => null,
        };
    }

    private static function formatYear(string $format): string
    {
        return match ($format) {
            'YYYY' => now()->format('Y'),
            'YY' => now()->format('y'),
            default => now()->format('Y'),
        };
    }

    private static function formatMonth(string $format): string
    {
        return match ($format) {
            'MM' => now()->format('m'),
            'M' => now()->format('n'),
            default => now()->format('m'),
        };
    }

    private static function formatSequential(string $format, int $number): string
    {
        $digits = (int)$format ?: 5;
        return str_pad($number, $digits, '0', STR_PAD_LEFT);
    }
}
