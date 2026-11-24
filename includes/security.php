<?php
/**
 * Security helpers: HTTPS, security headers, sessions, CSRF, XSS escaping,
 * authentication, role-based access control, login throttling and audit logging.
 */

/* ---------------- HTTPS + security headers ---------------- */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function send_security_headers(): void
{
    if (FORCE_HTTPS && !is_https() && PHP_SAPI !== 'cli') {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('X-Frame-Options: DENY');                 // clickjacking
    header('X-Content-Type-Options: nosniff');       // MIME sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');                   // hide PHP version
}

/* ---------------- Session management ---------------- */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('FBSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure'   => is_https(),   // cookie only sent over HTTPS when HTTPS is used
        'httponly' => true,         // JavaScript cannot read the cookie (limits XSS damage)
        'samesite' => 'Lax',        // limits cross-site request forgery
    ]);
    session_start();

    // Inactivity timeout
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        $wasLoggedIn = isset($_SESSION['user_id']);
        session_unset();
        session_destroy();
        session_start();
        if ($wasLoggedIn) {
            flash('warning', 'Your session expired due to inactivity. Please log in again.');
        }
    }
    $_SESSION['last_activity'] = time();

    // Periodically refresh the session ID
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - $_SESSION['created_at'] > 900) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

/* ---------------- XSS protection: escape all output ---------------- */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------- CSRF protection ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        audit('csrf_rejected', 'Request rejected: missing or invalid CSRF token');
        http_response_code(403);   // 403 Forbidden (non-standard 419 becomes 500 on Apache)
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

/* ---------------- Flash messages ---------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/* ---------------- Authentication ---------------- */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, full_name, email, phone, role, is_active, created_at, last_login FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            // Account deleted or deactivated after login: end the session
            if (!$user || !$user['is_active']) {
                $user = null;
                logout_user();
            }
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);   // prevents session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['created_at'] = time();
    unset($_SESSION['csrf_token']);
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- Access control ---------------- */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        audit('access_denied', 'Non-admin tried to open ' . ($_SERVER['REQUEST_URI'] ?? ''));
        forbidden();
    }
}

function forbidden(): never
{
    http_response_code(403);
    require ROOT_PATH . '/errors/403.php';
    exit;
}

function not_found(): never
{
    http_response_code(404);
    require ROOT_PATH . '/errors/404.php';
    exit;
}

/* ---------------- Brute-force protection ---------------- */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function too_many_attempts(string $email): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts
        WHERE (email = ? OR ip_address = ?) AND attempted_at > (NOW() - INTERVAL ? MINUTE)');
    $stmt->execute([$email, client_ip(), LOCKOUT_MINUTES]);
    return (int) $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

function record_failed_login(string $email): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')->execute([$email, client_ip()]);
}

function clear_failed_logins(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ? OR ip_address = ?')->execute([$email, client_ip()]);
}

/* ---------------- Audit / security log ---------------- */
function audit(string $action, string $details = ''): void
{
    try {
        db()->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'] ?? null, $action, mb_substr($details, 0, 500), client_ip(),
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    } catch (Throwable $t) {
        app_log('error', 'Audit log failed: ' . $t->getMessage());
    }
    if (in_array($action, ['login_failed', 'login_locked', 'access_denied', 'csrf_rejected'], true)) {
        app_log('security', "$action: $details");
    }
}
