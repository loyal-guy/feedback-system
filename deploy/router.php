<?php
/**
 * Router for PHP's built-in development server, so it applies the same protection
 * as .htaccess does on Apache. Usage (from the project folder):
 *     php -S localhost:8000 deploy/router.php
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$root = dirname(__DIR__);

// Block hidden files (.env, .git) and private folders, exactly like .htaccess
if (preg_match('#(^|/)\.|^/(config|includes|database|storage|tests|deploy|docs)(/|$)|\.(sql|md|log|sh|json|example)$#i', $path)) {
    http_response_code(404);
    require $root . '/errors/404.php';
    return true;
}

$file = realpath($root . $path);
if ($path === '/' ) {
    require $root . '/index.php';
    return true;
}
if ($file === false || !str_starts_with($file, $root)) {
    http_response_code(404);
    require $root . '/errors/404.php';
    return true;
}
return false; // let the built-in server handle the existing file
