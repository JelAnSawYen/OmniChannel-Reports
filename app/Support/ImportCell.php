<?php

namespace App\Support;

class ImportCell
{
    public static function isClear(string $raw): bool
    {
        return trim($raw) === '-';
    }

    /**
     * A row of blanks and lone dashes has no data to import.
     *
     * @param  array<string, mixed>  $values
     */
    public static function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '' && $text !== '-') {
                return false;
            }
        }

        return true;
    }
}
