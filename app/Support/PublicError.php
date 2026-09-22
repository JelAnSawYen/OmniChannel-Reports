<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class PublicError
{
    public static function failed(string $action, \Throwable $e): string
    {
        report($e);

        return $action.' failed. Please try again or contact an administrator.';
    }

    /**
     * Show the known validation reason when the failure is a rule the app already
     * describes. Anything unexpected keeps the safe generic message so database,
     * query, and filesystem details are never exposed.
     */
    public static function validationOrFailed(string $action, \Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            $messages = [];
            foreach ($e->errors() as $fieldMessages) {
                foreach ((array) $fieldMessages as $message) {
                    $message = trim((string) $message);
                    if ($message !== '') {
                        $messages[] = $message;
                    }
                }
            }
            if ($messages !== []) {
                return implode(' ', array_unique($messages));
            }
        }

        // Only messages the app authored itself are safe to show. Subclasses such as
        // PDOException / QueryException also extend RuntimeException and carry SQL.
        if ($e::class === RuntimeException::class && trim((string) $e->getMessage()) !== '') {
            return (string) $e->getMessage();
        }

        return self::failed($action, $e);
    }
}
