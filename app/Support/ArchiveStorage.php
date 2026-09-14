<?php

namespace App\Support;

use App\Models\ArchiveRecording;

class ArchiveStorage
{
    /**
     * Resolve a recording audio file inside the designated archive directories.
     */
    public static function resolveRecording(ArchiveRecording $recording): ?string
    {
        return self::firstContainedFile(
            self::recordingCandidates(
                (string) $recording->storage_path,
                (string) $recording->file_name,
            ),
            self::roots(),
        );
    }

    /**
     * Resolve a deletion certificate inside the designated archive directories.
     */
    public static function resolveCertificate(ArchiveRecording $recording): ?string
    {
        return self::firstContainedFile(
            self::certificateCandidates((string) $recording->certificate_path),
            self::roots(),
        );
    }

    /**
     * @return list<string>
     */
    public static function roots(): array
    {
        return [
            storage_path('app/private/archive-recordings'),
            storage_path('app/archive-recordings'),
        ];
    }

    /**
     * @param  list<string>  $candidates
     * @param  list<string>  $roots
     */
    public static function firstContainedFile(array $candidates, array $roots): ?string
    {
        foreach ($candidates as $candidate) {
            $resolved = self::containedFile($candidate, $roots);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $roots
     */
    public static function containedFile(string $path, array $roots): ?string
    {
        $path = self::sanitizePath($path);
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $real = realpath($path);
        if ($real === false || ! is_file($real)) {
            return null;
        }

        foreach ($roots as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }
            if (self::isInside($real, $realRoot)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function recordingCandidates(string $stored, string $fileName): array
    {
        $candidates = self::storedCandidates($stored);
        $baseName = self::safeBaseName($fileName);
        if ($baseName !== null) {
            foreach (self::roots() as $root) {
                $candidates[] = $root.DIRECTORY_SEPARATOR.$baseName;
            }
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private static function certificateCandidates(string $stored): array
    {
        return self::storedCandidates($stored);
    }

    /**
     * @return list<string>
     */
    private static function storedCandidates(string $stored): array
    {
        $stored = self::sanitizeInput($stored);
        if ($stored === null || self::hasTraversal($stored)) {
            return [];
        }

        if (self::isAbsolute($stored)) {
            return [$stored];
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $stored);
        $withoutPrefix = preg_replace('#^archive-recordings[\\\\/]#i', '', $relative) ?? $relative;

        $candidates = [
            storage_path('app'.DIRECTORY_SEPARATOR.$relative),
            storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.$relative),
        ];

        foreach (self::roots() as $root) {
            $candidates[] = $root.DIRECTORY_SEPARATOR.$withoutPrefix;
        }

        return $candidates;
    }

    private static function sanitizeInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, "\0")) {
            return null;
        }

        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($value);
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        $value = trim($value);
        if ($value === '' || str_contains($value, "\0")) {
            return null;
        }

        return $value;
    }

    private static function sanitizePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        return $path;
    }

    private static function safeBaseName(string $fileName): ?string
    {
        $fileName = self::sanitizeInput($fileName);
        if ($fileName === null || self::hasTraversal($fileName)) {
            return null;
        }

        $base = basename(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fileName));
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }

        return $base;
    }

    private static function hasTraversal(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private static function isInside(string $path, string $root): bool
    {
        $path = self::normalizeForCompare($path);
        $root = self::normalizeForCompare($root);

        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }

    private static function normalizeForCompare(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (PHP_OS_FAMILY === 'Windows') {
            return strtolower($path);
        }

        return $path;
    }
}
