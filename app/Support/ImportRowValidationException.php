<?php

namespace App\Support;

use RuntimeException;

/**
 * Raised when a Data Transfer confirmation re-validates rows and finds a known
 * problem. Carries the exact per-row messages so the Preview can show them
 * instead of a generic failure.
 */
class ImportRowValidationException extends RuntimeException
{
    /**
     * @param  array<int, list<string>>  $rowErrors  Excel row number => messages
     */
    public function __construct(private array $rowErrors)
    {
        parent::__construct(self::summarize($rowErrors));
    }

    /**
     * @return array<int, list<string>>
     */
    public function rowErrors(): array
    {
        return $this->rowErrors;
    }

    /**
     * @param  array<int, list<string>>  $rowErrors
     */
    private static function summarize(array $rowErrors): string
    {
        $parts = [];
        foreach ($rowErrors as $row => $messages) {
            $parts[] = 'Row '.$row.': '.implode('; ', $messages);
        }

        return $parts === []
            ? 'There are errors in some rows. Please review the details below and fix them in your file.'
            : implode(' ', $parts);
    }
}
