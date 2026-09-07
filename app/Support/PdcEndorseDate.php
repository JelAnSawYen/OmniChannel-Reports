<?php

namespace App\Support;

class PdcEndorseDate
{
    public const MIN_DATE = '2000-01-01';

    /**
     * Parse a Date Endorse value.
     *
     * @return array{valid: bool, empty: bool, iso: ?string, display: ?string}
     */
    public static function parse(?string $value): array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return ['valid' => true, 'empty' => true, 'iso' => null, 'display' => null];
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $match) === 1) {
            return self::fromParts((int) $match[3], (int) $match[1], (int) $match[2]);
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $match) === 1) {
            return self::fromParts((int) $match[1], (int) $match[2], (int) $match[3]);
        }

        if (preg_match('/^\d+(\.0+)?$/', $raw) === 1) {
            $serial = (int) $raw;
            if ($serial > 20000 && $serial < 80000) {
                $unix = ($serial - 25569) * 86400;
                $iso = gmdate('Y-m-d', $unix);
                [$year, $month, $day] = array_map('intval', explode('-', $iso));

                return self::fromParts($year, $month, $day);
            }
        }

        return ['valid' => false, 'empty' => false, 'iso' => null, 'display' => null];
    }

    /**
     * @return array{valid: bool, empty: bool, iso: ?string, display: ?string}
     */
    private static function fromParts(int $year, int $month, int $day): array
    {
        if ($year < 2000 || ! checkdate($month, $day, $year)) {
            return ['valid' => false, 'empty' => false, 'iso' => null, 'display' => null];
        }

        $iso = sprintf('%04d-%02d-%02d', $year, $month, $day);
        if ($iso < self::MIN_DATE) {
            return ['valid' => false, 'empty' => false, 'iso' => null, 'display' => null];
        }

        return [
            'valid' => true,
            'empty' => false,
            'iso' => $iso,
            'display' => $month.'/'.$day.'/'.$year,
        ];
    }

    public static function display(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        $parsed = self::parse($iso);

        return $parsed['display'] ?? '';
    }
}
