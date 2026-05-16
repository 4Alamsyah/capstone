<?php

namespace App\Services;

class WoNumberService
{
    public static function generate(array $format, int $sequenceNumber): string
    {
        $parts = [];
        $prefix = $format['prefix'] ?? 'WO';

        foreach ($format['components'] as $component) {
            $value = self::formatComponent($component, $sequenceNumber, $prefix);
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            }
        }

        return count($parts) > 0 ? implode($format['separator'] ?? '-', $parts) : 'WO-' . now()->format('Ym') . '-00001';
    }

    public static function preview(array $format): string
    {
        return self::generate($format, 1);
    }

    public static function stem(array $format): string
    {
        $parts = [];
        $prefix = $format['prefix'] ?? 'WO';

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
