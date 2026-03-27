<?php

namespace Arseno25\DocxBuilder\Support;

use Illuminate\Support\Str;

class FilenamePattern
{
    /**
     * Apply a filename pattern using a simple {key} replacement and sanitize.
     *
     * @param  array<string, mixed>  $context
     */
    public static function make(string $pattern, array $context, string $fallback = 'document'): string
    {
        $compiled = preg_replace_callback('/\\{([^}]+)\\}/', function (array $matches) use ($context) {
            $key = (string) ($matches[1] ?? '');

            $value = data_get($context, $key);
            if (is_array($value) || is_object($value)) {
                return '';
            }

            return (string) ($value ?? '');
        }, $pattern) ?? $pattern;

        $compiled = trim($compiled);
        if ($compiled === '') {
            $compiled = $fallback;
        }

        return self::sanitize($compiled) . '.docx';
    }

    public static function sanitize(string $filename): string
    {
        $filename = Str::of($filename)
            ->replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '-')
            ->replaceMatches('/\\s+/', ' ')
            ->trim()
            ->toString();

        $filename = preg_replace('/\\.+/', '.', $filename) ?? $filename;
        $filename = trim($filename, ". \t\n\r\0\x0B");

        if ($filename === '') {
            return 'document';
        }

        return $filename;
    }
}
