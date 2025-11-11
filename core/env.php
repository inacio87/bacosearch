<?php
// core/env.php - minimal .env loader
// Loads key=value pairs from project root .env into getenv/$_ENV/$_SERVER

if (!function_exists('load_env')) {
    function load_env(?string $path = null): void {
        $root = dirname(__DIR__); // project root
        $envPath = $path ?: $root . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return; // silently skip if no .env
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // allow comments after values KEY=VALUE # comment
            if (strpos($line, '#') !== false) {
                $line = preg_replace('/\s+#.*$/', '', $line);
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$key, $value] = $parts;
            $key = trim($key);
            $value = trim($value);
            // strip optional quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            // normalize Windows CRLF leftovers
            $value = rtrim($value, "\r\n");

            // export
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        return $val;
    }
}

// Auto-load .env at include time
load_env();
