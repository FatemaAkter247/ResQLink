<?php

// Load environment variables from the project-root .env file (if present).
// Returns an associative array of the parsed values so we don't depend on
// putenv()/getenv() (which some hardened PHP setups disable).
function resqlink_load_env()
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $vars = [];
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return $vars;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip optional surrounding quotes.
        if (strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key === '') {
            continue;
        }
        $vars[$key] = $value;
        // Best-effort export too (ignored if putenv is disabled).
        if (function_exists('putenv') && getenv($key) === false) {
            @putenv("$key=$value");
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
    return $vars;
}

// Resolve a config value: real environment first, then .env, then default.
function resqlink_env($key, $default = '')
{
    $env = resqlink_load_env();
    $fromOs = getenv($key);
    if ($fromOs !== false && $fromOs !== '') {
        return $fromOs;
    }
    if (isset($env[$key]) && $env[$key] !== '') {
        return $env[$key];
    }
    return $default;
}

define('AI_PROVIDER', 'gemini');

// Load from environment (.env or real environment variables)
define('GEMINI_API_KEY', resqlink_env('GEMINI_API_KEY', ''));
define('GEMINI_MODEL', 'gemini-flash-lite-latest');

define('OPENAI_API_KEY', resqlink_env('OPENAI_API_KEY', ''));
define('OPENAI_MODEL', 'gpt-4.1');

define('AI_MAX_OUTPUT_TOKENS', 700);
define('AI_TEMPERATURE', 0.25);
