<?php
// Load .env file (only once)
$envFile = __DIR__ . '/.env'; // adjust path if .env is outside web root

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Skip if no = sign
        if (strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);

        // Trim whitespace and quotes
        $name = trim($name);
        $value = trim($value);

        // Remove surrounding quotes if present
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Only set if not already set (allows overriding via real env vars / server)
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Helper to get env variables safely
function env($key, $default = null)
{
    $value = getenv($key);

    if ($value === false) {
        return $default instanceof Closure ? $default() : $default;
    }

    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    return $value;
}