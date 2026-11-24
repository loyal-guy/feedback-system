<?php
/**
 * Application configuration.
 * Values come from the .env file (environment variables) so that secrets such
 * as database passwords are never hard-coded in the source code.
 */

define('ROOT_PATH', dirname(__DIR__));

/** Minimal .env loader: KEY=VALUE per line, "#" starts a comment. */
function load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {          // real server env vars win over the file
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return match (strtolower($value)) {
        'true' => true,
        'false' => false,
        default => $value,
    };
}

load_env(ROOT_PATH . '/.env');

define('APP_ENV', env('APP_ENV', 'production'));
define('APP_NAME', env('APP_NAME', 'FeedbackHub'));
define('IS_PRODUCTION', APP_ENV === 'production');
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT_MINUTES', 30) * 60);
define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', 5));
define('LOCKOUT_MINUTES', (int) env('LOCKOUT_MINUTES', 15));
define('FORCE_HTTPS', (bool) env('FORCE_HTTPS', false));
define('LOG_FILE', ROOT_PATH . '/storage/logs/app.log');

/* ---------- Error handling: detailed in development, hidden + logged in production ---------- */
error_reporting(E_ALL);
ini_set('display_errors', IS_PRODUCTION ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

function app_log(string $level, string $message, array $context = []): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $line = sprintf("[%s] %s: %s %s (ip=%s)\n", date('Y-m-d H:i:s'), strtoupper($level), $message,
        $context ? json_encode($context) : '', $ip);
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

set_exception_handler(function (Throwable $e) {
    app_log('error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    http_response_code(500);
    if (!IS_PRODUCTION) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        require ROOT_PATH . '/errors/500.php';
    }
    exit;
});

/* ---------- Base URL (auto-detected so the app works in any XAMPP sub-folder) ---------- */
function detect_base_url(): string
{
    $configured = env('APP_BASE_URL', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $appRoot = realpath(ROOT_PATH) ?: ROOT_PATH;
    // Preferred: compare the running script's file path with its URL path.
    // Works with sub-folders, symlinks and Apache aliases.
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptFile !== '' && str_starts_with($scriptFile, $appRoot)) {
        $relative = str_replace('\\', '/', substr($scriptFile, strlen($appRoot)));
        if ($relative !== '' && str_ends_with($scriptName, $relative)) {
            return rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }
    // Fallback: compare the document root with the project folder.
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        return rtrim(str_replace('\\', '/', substr($appRoot, strlen($docRoot))), '/');
    }
    return '';
}
define('BASE_URL', detect_base_url());

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

date_default_timezone_set('Africa/Lagos');
