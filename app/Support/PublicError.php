<?php

namespace App\Support;

class PublicError
{
    public static function failed(string $action, \Throwable $e): string
    {
        report($e);

        return $action.' failed. Please try again or contact an administrator.';
    }
}
