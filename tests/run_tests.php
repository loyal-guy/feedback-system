<?php
/**
 * FeedbackHub automated test suite.
 *
 * Runs real HTTP requests against the running application and real queries
 * against the database, then saves the results to tests/results.json
 * (shown on the Testing page) and tests/TEST_REPORT.md.
 *
 * Usage (start Apache/MySQL in XAMPP first, or the PHP dev server):
 *     php tests/run_tests.php http://localhost/feedback-system
 *     php tests/run_tests.php http://localhost:8000
 *
 * Test accounts it creates are deleted at the end.
 */

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/db.php';

$BASE = rtrim($argv[1] ?? 'http://localhost/feedback-system', '/');
$results = [];
$perf = [];
$stamp = time();
$userA = ['name' => 'Test User Alpha', 'email' => "alpha{$stamp}@test.local", 'pass' => 'Alpha@1234'];
$userB = ['name' => 'Test User Beta',  'email' => "beta{$stamp}@test.local",  'pass' => 'Beta@12345'];
$ADMIN = ['email' => 'admin@feedbackhub.test', 'pass' => 'Admin@123'];

/* ------------------------------------------------------------------ */
/* Minimal HTTP client with its own cookie jar (one per "browser")    */
/* ------------------------------------------------------------------ */
class Browser
{
    public array $cookies = [];
    public array $headers = [];
    public int $status = 0;
    public string $body = '';
    public string $location = '';
    public float $ms = 0;

    public function __construct(private string $base) {}

    public function get(string $path): static { return $this->request('GET', $path); }

    public function post(string $path, array $data, bool $withToken = true): static
    {
        if ($withToken && !isset($data['csrf_token'])) {
            $data['csrf_token'] = $this->token($path);
        }
        return $this->request('POST', $path, $data);
    }

    /** Load the form page and read its CSRF token, like a real browser would. */
    public function token(string $path): string
    {
        $this->get($path);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m);
        return $m[1] ?? '';
    }

    public function request(string $method, string $path, array $data = []): static
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        $cookie = implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $cookie ? ["Cookie: $cookie"] : [],
            CURLOPT_HEADERFUNCTION => function ($ch, $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $this->headers[$k][] = trim($v);
                    if ($k === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', trim($v), $m)) {
                        if ($m[2] === '' || $m[2] === 'deleted') { unset($this->cookies[$m[1]]); }
                        else { $this->cookies[$m[1]] = $m[2]; }
                    }
                }
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $t = microtime(true);
        $this->body = (string) curl_exec($ch);
        $this->ms = (microtime(true) - $t) * 1000;
        if (curl_errno($ch)) {
            fwrite(STDERR, "Cannot reach {$this->base}: " . curl_error($ch) . "\nIs the server running?\n");
            exit(1);
        }
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = $this->headers['location'][0] ?? '';
        curl_close($ch);
        return $this;
    }

    public function follow(): static
    {
        $n = 0;
        while ($this->location && $n++ < 5) {
            $loc = $this->location;
            $path = preg_replace('#^https?://[^/]+#', '', $loc);
            $basePath = parse_url($this->base, PHP_URL_PATH) ?? '';
            if ($basePath && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
            $this->get($path);
        }
        return $this;
    }

    public function has(string $text): bool { return str_contains($this->body, $text); }
    public function header(string $name): string { return implode(' | ', $this->headers[strtolower($name)] ?? []); }
}

/* ------------------------------------------------------------------ */
function test(string $id, string $category, string $name, string $expected, callable $fn): void
{
    global $results;
    try {
        [$pass, $actual] = $fn();
    } catch (Throwable $e) {
        [$pass, $actual] = [false, 'Exception: ' . $e->getMessage()];
    }
    $results[] = compact('id', 'category', 'name', 'expected', 'actual', 'pass');
    printf("  %-4s %-6s %s\n", $id, $pass ? 'PASS' : 'FAIL', $name);
    if (!$pass) {
        echo "         expected: $expected\n         actual:   $actual\n";
    }
}

function clear_attempts(): void
{
    db()->exec("DELETE FROM login_attempts WHERE ip_address IN ('127.0.0.1','::1')");
}

function login(Browser $b, string $email, string $pass): Browser
{
    return $b->post('login.php', ['email' => $email, 'password' => $pass]);
}

function new_feedback(Browser $b, array $override = []): Browser
{
    return $b->post('feedback_new.php', array_merge([
        'category_id' => '1', 'subject' => 'Automated test feedback', 'rating' => '4',
        'message' => 'This feedback was created by the automated test suite.',
    ], $override));
}

function last_feedback_id(string $email): int
{
    $s = db()->prepare('SELECT f.id FROM feedback f JOIN users u ON u.id = f.user_id WHERE u.email = ? ORDER BY f.id DESC LIMIT 1');
    $s->execute([$email]);
    return (int) $s->fetchColumn();
}

echo "\nFeedbackHub test suite\nTarget: $BASE\nStarted: " . date('Y-m-d H:i:s') . "\n";
clear_attempts();

/* ================================================================== */
echo "\n[Database testing]\n";
test('D1', 'Database', 'Database connection', 'PDO connects using .env settings', function () {
    return [db()->query('SELECT 1')->fetchColumn() == 1, 'Connected to ' . env('DB_NAME')];
});
test('D2', 'Database', 'All required tables exist', '6 tables: users, categories, feedback, replies, login_attempts, activity_logs', function () {
    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $need = ['users', 'categories', 'feedback', 'replies', 'login_attempts', 'activity_logs'];
    $missing = array_diff($need, $tables);
    return [!$missing, $missing ? 'Missing: ' . implode(', ', $missing) : 'All 6 tables found'];
});
test('D3', 'Database', 'Unique email constraint', 'Inserting a duplicate email is rejected by the database', function () {
    try {
        db()->prepare("INSERT INTO users (full_name, email, password_hash) VALUES ('Dup', 'admin@feedbackhub.test', 'x')")->execute();
        return [false, 'Duplicate was inserted'];
    } catch (PDOException $e) {
        return [$e->getCode() == 23000, 'Rejected (SQLSTATE ' . $e->getCode() . ')'];
    }
});
test('D4', 'Database', 'Foreign key constraint', 'Feedback for a non-existent user is rejected', function () {
    try {
        db()->prepare("INSERT INTO feedback (user_id, category_id, subject, rating, message) VALUES (999999, 1, 'x', 3, 'x')")->execute();
        return [false, 'Orphan row was inserted'];
    } catch (PDOException $e) {
        return [$e->getCode() == 23000, 'Rejected (SQLSTATE ' . $e->getCode() . ')'];
    }
});
test('D5', 'Database', 'Rating CHECK constraint', 'A rating of 9 is rejected by the database', function () {
    try {
        db()->prepare("INSERT INTO feedback (user_id, category_id, subject, rating, message) VALUES (1, 1, 'x', 9, 'x')")->execute();
        db()->exec("DELETE FROM feedback WHERE rating = 9");
        return [false, 'Rating 9 was accepted (CHECK not enforced by this MySQL version)'];
    } catch (PDOException $e) {
        return [true, 'Rejected (SQLSTATE ' . $e->getCode() . ')'];
    }
});
test('D6', 'Database', 'Transaction rollback', 'Changes inside a rolled-back transaction are not saved', function () {
    $pdo = db();
    $before = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO categories (name) VALUES ('Rollback Test')");
    $pdo->rollBack();
    $after = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    return [$before === $after, "Rows before: $before, after rollback: $after"];
});

/* ================================================================== */
echo "\n[Login & registration testing]\n";
$a = new Browser($BASE);
test('L1', 'Login/Registration', 'Register with valid details', 'Account created, redirected to login with success message', function () use ($a, $userA) {
    $a->post('register.php', ['full_name' => $userA['name'], 'email' => $userA['email'], 'phone' => '08011112222',
        'password' => $userA['pass'], 'password_confirm' => $userA['pass'], 'terms' => '1']);
    $redirect = str_contains($a->location, 'login.php');
    $a->follow();
    return [$redirect && $a->has('Account created successfully'), $redirect ? 'Redirected to login, success message shown' : "HTTP {$a->status}, no redirect"];
});
test('L2', 'Login/Registration', 'Register with an existing email', '"An account with this email already exists"', function () use ($userA) {
    $b = new Browser($GLOBALS['BASE']);
    $b->post('register.php', ['full_name' => 'Someone Else', 'email' => $userA['email'], 'phone' => '',
        'password' => 'Other@1234', 'password_confirm' => 'Other@1234', 'terms' => '1']);
    return [$b->has('already exists'), $b->has('already exists') ? 'Duplicate email error displayed' : 'No error shown'];
});
test('L3', 'Login/Registration', 'Valid login', 'User is taken to the dashboard', function () use ($a, $userA) {
    login($a, $userA['email'], $userA['pass']);
    $ok = str_contains($a->location, 'dashboard.php');
    $a->follow();
    return [$ok && $a->has('Hello, ' . $userA['name']), $ok ? 'Dashboard displayed with user name' : 'Not redirected to dashboard'];
});
test('L4', 'Login/Registration', 'Login with wrong password', '"Invalid email or password" shown, no login', function () use ($userA) {
    $b = new Browser($GLOBALS['BASE']);
    login($b, $userA['email'], 'Wrong@Pass1');
    return [$b->status === 200 && $b->has('Invalid email or password'), 'Error message displayed, stayed on login page'];
});
test('L5', 'Login/Registration', 'Login with unregistered email', 'Same generic error as wrong password (no account enumeration)', function () {
    $b = new Browser($GLOBALS['BASE']);
    login($b, 'nobody' . time() . '@test.local', 'Some@Pass1');
    return [$b->has('Invalid email or password'), 'Generic "Invalid email or password" displayed'];
});
test('L6', 'Login/Registration', 'Admin login', 'Admin is taken to the admin dashboard', function () {
    clear_attempts();
    $b = new Browser($GLOBALS['BASE']);
    login($b, $GLOBALS['ADMIN']['email'], $GLOBALS['ADMIN']['pass']);
    $ok = str_contains($b->location, 'admin/index.php');
    $b->follow();
    return [$ok && $b->has('Admin dashboard'), $ok ? 'Admin dashboard displayed' : 'Wrong redirect: ' . $b->location];
});
test('L7', 'Login/Registration', 'Logout', 'Session ends; dashboard then redirects to login', function () use ($userA) {
    $b = new Browser($GLOBALS['BASE']);
    login($b, $userA['email'], $userA['pass']);
    $b->post('logout.php', ['csrf_token' => $b->token('dashboard.php')]);
    $b->get('dashboard.php');
    return [str_contains($b->location, 'login.php'), 'After logout, dashboard redirects to login.php'];
});
clear_attempts();

/* ================================================================== */
echo "\n[Form validation testing]\n";
$reg = fn(array $d) => (new Browser($GLOBALS['BASE']))->post('register.php', array_merge(
    ['full_name' => 'Valid Name', 'email' => 'v' . mt_rand() . '@test.local', 'phone' => '', 'password' => 'Valid@1234', 'password_confirm' => 'Valid@1234', 'terms' => '1'], $d));
test('V1', 'Form validation', 'Empty registration fields', 'Required-field messages displayed', function () use ($reg) {
    $b = $reg(['full_name' => '', 'email' => '', 'password' => '', 'password_confirm' => '']);
    $ok = $b->has('Full name is required') && $b->has('Email is required') && $b->has('Password is required');
    return [$ok, $ok ? 'Messages shown for name, email and password' : 'Messages missing'];
});
test('V2', 'Form validation', 'Invalid email format', '"Enter a valid email address"', function () use ($reg) {
    $b = $reg(['email' => 'not-an-email']);
    return [$b->has('Enter a valid email address'), 'Email format error displayed'];
});
test('V3', 'Form validation', 'Weak password', 'Password rule message; account not created', function () use ($reg) {
    $b = $reg(['password' => 'password', 'password_confirm' => 'password']);
    return [$b->has('Password must be 8-72 characters'), 'Strong-password rule message displayed'];
});
test('V4', 'Form validation', 'Passwords do not match', '"Passwords do not match"', function () use ($reg) {
    $b = $reg(['password_confirm' => 'Different@1']);
    return [$b->has('Passwords do not match'), 'Mismatch message displayed'];
});
test('V5', 'Form validation', 'Invalid name and phone', 'Name and phone format errors', function () use ($reg) {
    $b = $reg(['full_name' => 'J0hn<>123', 'phone' => '12ab']);
    $ok = $b->has('may contain only letters') && $b->has('valid phone number');
    return [$ok, $ok ? 'Both format errors displayed' : 'Errors missing'];
});
test('V6', 'Form validation', 'Feedback rating outside 1-5', 'Rating error; nothing saved', function () use ($a, $userA) {
    $before = last_feedback_id($userA['email']);
    new_feedback($a, ['rating' => '9']);
    $ok = $a->has('Rating must be a whole number from 1 to 5') && last_feedback_id($userA['email']) === $before;
    return [$ok, $ok ? 'Rejected with rating error' : 'Rating 9 accepted'];
});
test('V7', 'Form validation', 'Feedback with tampered category id', 'Category error; nothing saved', function () use ($a) {
    new_feedback($a, ['category_id' => '9999']);
    return [$a->has('Select a valid category'), 'Invalid category rejected'];
});
test('V8', 'Form validation', 'Feedback message too short', 'Length error shown', function () use ($a) {
    new_feedback($a, ['message' => 'Bad']);
    return [$a->has('Message must be between 10 and 2000 characters'), 'Length error displayed'];
});

/* ================================================================== */
echo "\n[Functional testing]\n";
test('F1', 'Functional', 'Home page loads', 'HTTP 200 with statistics', function () {
    $b = (new Browser($GLOBALS['BASE']))->get('index.php');
    $GLOBALS['perf']['Home page'] = $b->ms;
    return [$b->status === 200 && $b->has('Feedback received'), "HTTP {$b->status}, statistics shown"];
});
test('F2', 'Functional', 'Submit valid feedback', 'Saved and the detail page is shown', function () use ($a, $userA) {
    new_feedback($a, ['subject' => 'Checkout keeps timing out']);
    $ok = str_contains($a->location, 'feedback_view.php');
    $a->follow();
    return [$ok && $a->has('Checkout keeps timing out') && $a->has('Pending'), $ok ? 'Feedback saved with status Pending' : 'Not saved'];
});
test('F3', 'Functional', 'Feedback appears in "My feedback"', 'New feedback listed', function () use ($a) {
    $a->get('my_feedback.php');
    $GLOBALS['perf']['My feedback list'] = $a->ms;
    return [$a->has('Checkout keeps timing out'), 'Listed in the table'];
});
test('F4', 'Functional', 'Search and filter', 'Matching item shown; non-matching hidden', function () use ($a) {
    new_feedback($a, ['subject' => 'Lovely staff at branch', 'rating' => '5', 'category_id' => '2']);
    $a->get('my_feedback.php?q=' . urlencode('Checkout'));
    $hit = $a->has('Checkout keeps timing out') && !$a->has('Lovely staff');
    $a->get('my_feedback.php?rating=5');
    $filter = $a->has('Lovely staff') && !$a->has('Checkout keeps timing out');
    return [$hit && $filter, ($hit ? 'Keyword search correct' : 'Keyword search wrong') . '; ' . ($filter ? 'rating filter correct' : 'rating filter wrong')];
});
test('F5', 'Functional', 'Admin replies and resolves feedback', 'Customer sees the reply and status "Resolved"', function () use ($a, $userA) {
    clear_attempts();
    $adm = new Browser($GLOBALS['BASE']);
    login($adm, $GLOBALS['ADMIN']['email'], $GLOBALS['ADMIN']['pass']);
    $id = last_feedback_id($userA['email']);
    $adm->post("admin/feedback_view.php?id=$id", ['action' => 'reply', 'message' => 'Thank you, this has been fixed.', 'status' => 'Resolved',
        'csrf_token' => $adm->token("admin/feedback_view.php?id=$id")]);
    $a->get("feedback_view.php?id=$id");
    $ok = $a->has('Thank you, this has been fixed.') && $a->has('badge-green">Resolved');
    return [$ok, $ok ? 'Reply visible to customer, status Resolved' : 'Reply or status missing'];
});
test('F6', 'Functional', 'Update profile', 'New name saved and shown', function () use ($a) {
    $a->post('profile.php', ['action' => 'profile', 'full_name' => 'Test User Updated', 'phone' => '08099998888']);
    $a->follow();
    return [$a->has('Profile updated') && $a->has('Test User Updated'), 'Profile updated message and new name shown'];
});
test('F7', 'Functional', 'Change password', 'Old password stops working, new one works', function () use ($a, $userA) {
    $new = 'Changed@5678';
    $a->post('profile.php', ['action' => 'password', 'current_password' => $userA['pass'], 'new_password' => $new, 'confirm_password' => $new]);
    $a->follow();
    $changed = $a->has('Password changed successfully');
    $b = new Browser($GLOBALS['BASE']);
    login($b, $userA['email'], $new);
    $GLOBALS['userA']['pass'] = $new;
    return [$changed && str_contains($b->location, 'dashboard.php'), $changed ? 'Changed; login with new password works' : 'Not changed'];
});
test('F8', 'Functional', 'Admin dashboard, charts and CSV export', 'Dashboard renders charts; export returns a CSV file', function () {
    clear_attempts();
    $adm = new Browser($GLOBALS['BASE']);
    login($adm, $GLOBALS['ADMIN']['email'], $GLOBALS['ADMIN']['pass']);
    $adm->get('admin/index.php');
    $GLOBALS['perf']['Admin dashboard'] = $adm->ms;
    $charts = substr_count($adm->body, 'data-chart=');
    $adm->get('admin/feedback.php?q=test');
    $GLOBALS['perf']['Admin feedback search'] = $adm->ms;
    $adm->get('admin/export.php');
    $csv = str_contains($adm->header('content-type'), 'text/csv') && str_contains($adm->body, 'ID,Date,Customer');
    return [$charts === 4 && $csv, "$charts charts rendered; CSV " . ($csv ? 'downloaded' : 'failed')];
});

/* ================================================================== */
echo "\n[Security testing]\n";
test('S1', 'Security', 'SQL injection in login form', "Input  ' OR '1'='1' --  does not log in", function () {
    $b = new Browser($GLOBALS['BASE']);
    login($b, "' OR '1'='1' -- ", "' OR '1'='1' -- ");
    $ok = !str_contains($b->location, 'dashboard') && !str_contains($b->location, 'admin') && $b->has('Invalid email or password');
    return [$ok, $ok ? 'Login refused; treated as a normal (wrong) email' : 'Login bypassed!'];
});
clear_attempts();
test('S2', 'Security', 'SQL injection in search box', 'No database error; payload treated as text', function () use ($a) {
    $a->get('my_feedback.php?q=' . urlencode("x' UNION SELECT password_hash,1,1,1,1,1,1 FROM users -- "));
    $ok = $a->status === 200 && !$a->has('$2y$') && !$a->has('SQLSTATE') && $a->has('No feedback matches');
    return [$ok, $ok ? 'HTTP 200, no hashes or SQL errors leaked, zero results' : 'Possible injection'];
});
test('S3', 'Security', 'Stored XSS in feedback', '<script> saved but displayed as harmless text', function () use ($a) {
    new_feedback($a, ['subject' => '<script>alert("xss")</script> test', 'message' => '<img src=x onerror=alert(1)> hello there']);
    $a->follow();
    $ok = !$a->has('<script>alert("xss")</script>') && $a->has('&lt;script&gt;') && !$a->has('<img src=x');
    return [$ok, $ok ? 'Output escaped as &lt;script&gt; - script does not run' : 'Raw script found in page!'];
});
test('S4', 'Security', 'Reflected XSS in search parameter', 'Search term escaped in the page', function () use ($a) {
    $a->get('my_feedback.php?q=' . urlencode('"><script>alert(1)</script>'));
    $ok = !$a->has('<script>alert(1)</script>') && $a->has('&quot;&gt;&lt;script&gt;');
    return [$ok, $ok ? 'Escaped in the search box value' : 'Payload reflected unescaped'];
});
test('S5', 'Security', 'CSRF: form submitted without token', 'Request rejected with HTTP 403', function () use ($a) {
    $a->post('feedback_new.php', ['category_id' => '1', 'subject' => 'CSRF attempt here', 'rating' => '1', 'message' => 'Forged request from another site'], false);
    return [$a->status === 403 && $a->has('Invalid or expired form token'), "HTTP {$a->status}"];
});
test('S6', 'Security', 'CSRF: forged token', 'Request rejected with HTTP 403', function () use ($a) {
    $a->post('profile.php', ['action' => 'profile', 'full_name' => 'Hacked Name', 'csrf_token' => str_repeat('a', 64)]);
    return [$a->status === 403 && $a->has('Invalid or expired form token'), "HTTP {$a->status}"];
});
test('S7', 'Security', 'Unauthorised admin page (logged in as user)', 'Access denied (HTTP 403)', function () use ($a) {
    $codes = [];
    foreach (['admin/index.php', 'admin/users.php', 'admin/logs.php', 'admin/export.php'] as $p) {
        $codes[] = $a->get($p)->status;
    }
    return [array_unique($codes) === [403], 'HTTP ' . implode(', ', $codes) . ' for 4 admin pages'];
});
test('S8', 'Security', 'Protected page without login', 'Redirected to login page', function () {
    $b = (new Browser($GLOBALS['BASE']))->get('dashboard.php');
    $c = (new Browser($GLOBALS['BASE']))->get('admin/index.php');
    $ok = str_contains($b->location, 'login.php') && str_contains($c->location, 'login.php');
    return [$ok, $ok ? 'Both redirected to login.php' : 'Page opened without login'];
});
test('S9', 'Security', "IDOR: opening another user's feedback", 'Access denied (HTTP 403)', function () use ($userA, $userB) {
    $b = new Browser($GLOBALS['BASE']);
    $b->post('register.php', ['full_name' => $userB['name'], 'email' => $userB['email'], 'phone' => '',
        'password' => $userB['pass'], 'password_confirm' => $userB['pass'], 'terms' => '1']);
    login($b, $userB['email'], $userB['pass']);
    $id = last_feedback_id($userA['email']);
    $b->get("feedback_view.php?id=$id");
    return [$b->status === 403 && !$b->has('hello there'), "User B got HTTP {$b->status} for User A's feedback #$id"];
});
test('S10', 'Security', 'Passwords stored as hashes', 'Database holds a bcrypt hash, not the password', function () use ($userB) {
    $s = db()->prepare('SELECT password_hash FROM users WHERE email = ?');
    $s->execute([$userB['email']]);
    $h = (string) $s->fetchColumn();
    return [str_starts_with($h, '$2y$') && $h !== $userB['pass'] && password_verify($userB['pass'], $h), 'Stored: ' . substr($h, 0, 20) . '… (bcrypt)'];
});
test('S11', 'Security', 'Role cannot be chosen at registration', 'Extra field role=admin is ignored; account is "user"', function () {
    $email = 'sneaky' . time() . '@test.local';
    (new Browser($GLOBALS['BASE']))->post('register.php', ['full_name' => 'Sneaky Person', 'email' => $email, 'phone' => '',
        'password' => 'Sneaky@123', 'password_confirm' => 'Sneaky@123', 'terms' => '1', 'role' => 'admin']);
    $s = db()->prepare('SELECT role FROM users WHERE email = ?');
    $s->execute([$email]);
    $role = $s->fetchColumn();
    db()->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    return [$role === 'user', "Account created with role: $role"];
});
test('S12', 'Security', 'Security headers sent', 'CSP, X-Frame-Options, nosniff, Referrer-Policy present; no X-Powered-By', function () {
    $b = (new Browser($GLOBALS['BASE']))->get('index.php');
    $need = ['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy'];
    $missing = array_filter($need, fn($h) => $b->header($h) === '');
    $leak = $b->header('x-powered-by') !== '';
    return [!$missing && !$leak, $missing ? 'Missing: ' . implode(', ', $missing) : 'All headers present' . ($leak ? ', but X-Powered-By leaked' : ', PHP version hidden')];
});
test('S13', 'Security', 'Session cookie flags', 'Cookie is HttpOnly and SameSite=Lax', function () {
    $b = (new Browser($GLOBALS['BASE']))->get('login.php');
    $c = strtolower($b->header('set-cookie'));
    $ok = str_contains($c, 'httponly') && str_contains($c, 'samesite=lax');
    return [$ok, $ok ? 'HttpOnly; SameSite=Lax' : "Cookie: $c"];
});
test('S14', 'Security', 'Session fixation prevented', 'Session ID changes after login', function () use ($userB) {
    $b = new Browser($GLOBALS['BASE']);
    $b->get('login.php');
    $before = $b->cookies['FBSESSID'] ?? '';
    login($b, $userB['email'], $userB['pass']);
    $after = $b->cookies['FBSESSID'] ?? '';
    return [$before !== '' && $after !== '' && $before !== $after, 'ID before and after login are different'];
});
test('S15', 'Security', 'Sensitive files blocked from the web', '.env, database/schema.sql and logs return 403/404', function () {
    $codes = [];
    foreach (['.env', 'database/schema.sql', 'storage/logs/app.log', 'config/config.php', 'tests/results.json'] as $p) {
        $b = (new Browser($GLOBALS['BASE']))->get($p);
        $leak = str_contains($b->body, 'DB_PASS') || str_contains($b->body, 'CREATE TABLE');
        $codes[] = $leak ? 'LEAK' : $b->status;
    }
    $ok = !array_diff($codes, [403, 404]);
    return [$ok, 'Responses: ' . implode(', ', $codes)];
});
test('S16', 'Security', 'Deactivated account cannot log in', 'Login refused with "deactivated" message', function () use ($userB) {
    db()->prepare('UPDATE users SET is_active = 0 WHERE email = ?')->execute([$userB['email']]);
    $b = new Browser($GLOBALS['BASE']);
    login($b, $userB['email'], $userB['pass']);
    db()->prepare('UPDATE users SET is_active = 1 WHERE email = ?')->execute([$userB['email']]);
    return [$b->has('deactivated') && !$b->location, 'Login refused, message displayed'];
});
test('S17', 'Security', 'Brute-force lockout', 'After 5 wrong passwords even the correct one is blocked', function () use ($userB) {
    clear_attempts();
    $b = new Browser($GLOBALS['BASE']);
    for ($i = 0; $i < MAX_LOGIN_ATTEMPTS; $i++) {
        login($b, $userB['email'], 'Wrong@' . $i . 'xx');
    }
    login($b, $userB['email'], $userB['pass']);
    $ok = $b->has('Too many failed attempts') && !$b->location;
    clear_attempts();
    return [$ok, $ok ? 'Blocked after ' . MAX_LOGIN_ATTEMPTS . ' failures' : 'Not blocked'];
});
test('S18', 'Security', 'Security events are logged', 'Failed logins and denied access appear in activity_logs', function () {
    $n = db()->query("SELECT COUNT(DISTINCT action) FROM activity_logs WHERE action IN ('login_failed','access_denied','csrf_rejected','login_locked') AND created_at > NOW() - INTERVAL 10 MINUTE")->fetchColumn();
    return [(int) $n === 4, "$n of 4 security event types recorded in the last 10 minutes"];
});

/* ================================================================== */
echo "\n[Error handling testing]\n";
test('E1', 'Error handling', 'Feedback that does not exist', 'Friendly 404 page', function () use ($a) {
    $a->get('feedback_view.php?id=99999999');
    return [$a->status === 404 && $a->has('Page not found'), "HTTP {$a->status}, friendly page shown"];
});
test('E2', 'Error handling', 'Invalid id value (text instead of number)', 'Friendly 404 page, no PHP error', function () use ($a) {
    $a->get('feedback_view.php?id=abc');
    $ok = $a->status === 404 && !preg_match('/Warning|Fatal|Notice|Stack trace/', $a->body);
    return [$ok, "HTTP {$a->status}, no PHP errors"];
});
test('E3', 'Error handling', 'Array injection in parameters', 'Handled without PHP errors', function () use ($a) {
    $a->get('my_feedback.php?q[]=1&status[]=x');
    $ok = $a->status === 200 && !preg_match('/Warning|Fatal|Notice|TypeError/', $a->body);
    return [$ok, "HTTP {$a->status}, input safely ignored"];
});
test('E4', 'Error handling', 'Delete resolved feedback (business rule)', '"Only pending feedback can be deleted"', function () use ($a, $userA) {
    $s = db()->prepare("SELECT f.id FROM feedback f JOIN users u ON u.id=f.user_id WHERE u.email = ? AND f.status = 'Resolved' LIMIT 1");
    $s->execute([$userA['email']]);
    $id = (int) $s->fetchColumn();
    $a->post("feedback_view.php?id=$id", ['id' => $id, 'action' => 'delete', 'csrf_token' => $a->token("feedback_view.php?id=$id")]);
    $a->follow();
    return [$a->has('Only pending feedback can be deleted'), 'Rule enforced, message shown'];
});

/* ------------------------------------------------------------------ */
// Clean up test accounts (feedback and replies are removed by ON DELETE CASCADE)
db()->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$userA['email'], $userB['email']]);
db()->exec("DELETE FROM users WHERE email LIKE '%@test.local'");
clear_attempts();

$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['pass']));
$perfRounded = array_map(fn($v) => round($v, 1), $perf);
$summary = ['run_at' => date('Y-m-d H:i:s'), 'target' => $BASE, 'total' => $total, 'passed' => $passed,
    'failed' => $total - $passed, 'php' => PHP_VERSION, 'performance_ms' => $perfRounded, 'tests' => $results];
file_put_contents(__DIR__ . '/results.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// Markdown report for the project write-up
$md = "# FeedbackHub automated test report\n\nRun: {$summary['run_at']}  \nTarget: $BASE  \nResult: **$passed / $total passed**\n\n";
$md .= "| ID | Category | Test | Expected result | Actual result | Status |\n|---|---|---|---|---|---|\n";
foreach ($results as $r) {
    $md .= sprintf("| %s | %s | %s | %s | %s | %s |\n", $r['id'], $r['category'], $r['name'],
        str_replace('|', '\|', $r['expected']), str_replace('|', '\|', $r['actual']), $r['pass'] ? 'Pass' : 'Fail');
}
$md .= "\n## Response times\n\n| Page | Time (ms) |\n|---|---|\n";
foreach ($perfRounded as $p => $ms) {
    $md .= "| $p | $ms |\n";
}
file_put_contents(__DIR__ . '/TEST_REPORT.md', $md);

echo "\n==============================\n Result: $passed / $total passed\n==============================\n";
echo "Saved tests/results.json and tests/TEST_REPORT.md\n\n";
exit($passed === $total ? 0 : 1);
