<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class ProgramLocationStatus
{
    public static function assigned(string $slug, bool $default): bool
    {
        $overrides = self::all();

        if (! array_key_exists($slug, $overrides)) {
            return $default;
        }

        return (bool) $overrides[$slug];
    }

    public static function set(string $slug, bool $assigned): void
    {
        $overrides = self::all();
        $overrides[$slug] = $assigned;
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode($overrides, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $path = self::path();
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function path(): string
    {
        return storage_path('app/program-location-assigned.json');
    }
}
