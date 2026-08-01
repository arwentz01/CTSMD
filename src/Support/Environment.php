<?php

declare(strict_types=1);

namespace App\Support;

final class Environment
{
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || isset($_ENV[$key])) {
                continue;
            }

            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

