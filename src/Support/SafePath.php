<?php

namespace AmjadIqbal\FilamentRecordPdf\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * File names often come from record data (an invoice number, a customer name), so every name and
 * directory is cleaned here before it touches a disk.
 */
class SafePath
{
    /**
     * A file name with no directory part, only [a-z0-9-], and the given extension.
     */
    public static function filename(?string $name, string $extension = 'pdf', string $fallback = 'document'): string
    {
        $name = str_replace(["\0", '/', '\\'], '-', (string) $name);
        $name = preg_replace('/\.'.preg_quote($extension, '/').'$/i', '', trim($name)) ?? '';
        $name = Str::limit(Str::slug(Str::ascii($name)), 150, '');
        $name = trim($name, '-');

        return ($name === '' ? $fallback : $name).'.'.$extension;
    }

    /**
     * A relative directory: no leading slash, no drive or scheme, no "." or ".." segments.
     *
     * @throws InvalidArgumentException
     */
    public static function directory(?string $directory): string
    {
        if (str_contains((string) $directory, "\0")) {
            throw new InvalidArgumentException('Unsafe PDF directory: it contains a NUL byte.');
        }

        $directory = str_replace('\\', '/', trim((string) $directory));

        if ($directory === '') {
            return '';
        }

        if (str_starts_with($directory, '/')
            || str_contains($directory, '://')
            || preg_match('/^[A-Za-z]:/', $directory)) {
            throw new InvalidArgumentException("Unsafe PDF directory [{$directory}]: use a path relative to the disk root.");
        }

        $segments = array_values(array_filter(explode('/', $directory), fn (string $segment): bool => $segment !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || ! preg_match('/^[A-Za-z0-9 _.\-]+$/', $segment)) {
                throw new InvalidArgumentException("Unsafe PDF directory [{$directory}]: segment [{$segment}] is not allowed.");
            }
        }

        return implode('/', $segments);
    }

    public static function join(string $directory, string $filename): string
    {
        $directory = static::directory($directory);

        if ($filename !== basename($filename) || $filename === '..' || $filename === '.') {
            throw new InvalidArgumentException("Unsafe PDF file name [{$filename}].");
        }

        return ltrim($directory.'/'.$filename, '/');
    }

    /**
     * True when $path is a file directly or deeper inside $directory, with no traversal.
     */
    public static function isInside(string $path, string $directory): bool
    {
        try {
            $clean = static::directory(dirname($path));
            $root = static::directory($directory);
        } catch (InvalidArgumentException) {
            return false;
        }

        $file = basename($path);

        if ($file === '' || $file === '.' || $file === '..' || $path !== ltrim($clean.'/'.$file, '/')) {
            return false;
        }

        return $root === '' || $clean === $root || str_starts_with($clean.'/', $root.'/');
    }
}
