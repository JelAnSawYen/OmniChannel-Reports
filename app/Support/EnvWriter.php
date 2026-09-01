<?php

namespace App\Support;

class EnvWriter
{
    /**
     * @param  array<string, string>  $pairs
     */
    public static function set(array $pairs): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($pairs as $key => $value) {
            $line = $key.'='.self::export((string) $value);
            $pattern = '/^'.preg_quote((string) $key, '/').'=.*/m';
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $line, $contents) ?? $contents;
            } else {
                $contents = rtrim($contents)."\n".$line."\n";
            }
        }

        file_put_contents($path, $contents);
    }

    private static function export(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.@+-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
