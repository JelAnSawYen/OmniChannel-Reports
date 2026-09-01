<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public static function required(): array
    {
        return ['required', 'string', 'confirmed', self::rule()];
    }

    public static function optional(): array
    {
        return ['nullable', 'string', 'confirmed', self::rule()];
    }

    public static function rule(): Password
    {
        return Password::min(12)->letters()->mixedCase()->numbers()->symbols();
    }
}
