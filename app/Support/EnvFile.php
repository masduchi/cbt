<?php

namespace App\Support;

class EnvFile
{
    public static function path(): string
    {
        return (string) config('installer.env_path', base_path('.env'));
    }

    public static function writable(): bool
    {
        $path = self::path();

        return is_file($path) ? is_writable($path) : is_writable(dirname($path));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        $path = self::path();
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.self::format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        file_put_contents($path, $content);
    }

    private static function format(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_match('/[\s#"\'\\\\]/', $value) === 1
            ? '"'.addcslashes($value, '"\\').'"'
            : $value;
    }
}
