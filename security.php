<?php
require __DIR__ . '/includes/bootstrap.php';

$measures = [
    ['Authentication (login / logout)', 'login.php, logout.php, includes/security.php',
        'Users log in with email and password. Logout destroys the session and deletes the session cookie. Logout only works through a POST form with a CSRF token, so another website cannot log a user out.'],
    ['Password hashing', 'register.php, profile.php',
        'Passwords are hashed with <code>password_hash()</code> (bcrypt, salted automatically) and checked with <code>password_verify()</code>. The plain password is never stored or logged. Passwords must be 8+ characters with upper case, lower case, a number and a symbol. Old hashes are upgraded automatically with <code>password_needs_rehash()</code>.'],
    ['Role-based access control', 'require_admin() in includes/security.php',
        'Each user has the role <strong>user</strong> or <strong>admin</strong>. Every page in <code>/admin</code> calls <code>require_admin()</code>; customers who try to open them get a 403 page and the attempt is logged. New accounts are always created as "user"; the role cannot be chosen on the form.'],
    ['Access-control checks (IDOR prevention)', 'feedback_view.php',
        'When a customer opens a feedback record, the server checks that the record belongs to them. Changing <code>?id=5</code> to another number in the address bar returns "Access denied".'],
    ['Input validation', 'includes/validation.php',
        'Every form is validated on the server: required fields, length limits, email format, phone format, name characters, rating 1-5, and category/status values from an allowed list. HTML5 checks in the browser are only for convenience, because they can be bypassed.'],
    ['SQL injection protection', 'includes/db.php and every query',
        'All database queries use PDO prepared statements with bound parameters, and emulated prepares are switched off. User input is never joined into SQL text. Sort options are chosen from a fixed list instead of taking a column name from the URL.'],
    ['Cross-Site Scripting (XSS) protection', 'e() in includes/security.php',
        'All output is escaped with <code>htmlspecialchars()</code> through the <code>e()</code> helper, so <code>&lt;script&gt;</code> typed in a feedback message is shown as text and never runs. A Content-Security-Policy header blocks inline and third-party scripts as a second layer of defence.'],
    ['CSRF protection', 'csrf_field() / verify_csrf()',
        'Every form that changes data includes a random 64-character token stored in the session. Requests with a missing or wrong token are rejected with HTTP 403 and logged. Session cookies also use <code>SameSite=Lax</code>.'],
    ['Session management', 'start_secure_session()',
        'Session cookies are <code>HttpOnly</code> (JavaScript cannot read them), <code>Secure</code> when HTTPS is on, and <code>SameSite=Lax</code>. The session ID is regenerated at login (prevents session fixation) and every 15 minutes. Sessions end after ' . (SESSION_TIMEOUT / 60) . ' minutes of inactivity. Strict mode rejects session IDs the server did not create.'],
    ['Brute-force protection', 'too_many_attempts()',
        'After ' . MAX_LOGIN_ATTEMPTS . ' failed logins for an email or IP address, logins are blocked for ' . LOCKOUT_MINUTES . ' minutes. The error message is the same for a wrong email and a wrong password, so attackers cannot find out which emails are registered.'],
    ['Secure handling of sensitive information', '.env, config/config.php, .htaccess',
        'Database passwords and settings live in a <code>.env</code> file outside the code, which <code>.htaccess</code> blocks from the web. In production, error details are hidden from users and written to a log file instead. The PHP version header is removed, and CSV exports are protected against formula injection.'],
    ['HTTPS and security headers', 'send_security_headers(), .htaccess',
        'With <code>FORCE_HTTPS=true</code>, all HTTP requests are redirected to HTTPS and an HSTS header is sent. Every response includes <code>Content-Security-Policy</code>, <code>X-Frame-Options: DENY</code> (clickjacking), <code>X-Content-Type-Options: nosniff</code>, <code>Referrer-Policy</code> and <code>Permissions-Policy</code>.'],
    ['Audit logging and monitoring', 'audit(), admin/logs.php',
        'Logins, failed logins, lockouts, denied access, rejected CSRF tokens and admin actions are recorded with time, user and IP address. Admins can review them on the Activity Logs page, and the dashboard shows security counts for the last 24 hours.'],
];

$pageTitle = 'Security';
require __DIR__ . '/includes/header.php';
?>
<div class="doc">
<div class="page-head"><div><h1>Web application security</h1><p class="muted">The security measures implemented in FeedbackHub, where to find them in the code, and how each one was tested.</p></div></div>

<section class="card">
    <h2>Live security status of this server</h2>
    <dl class="meta-list">
        <dt>Connection</dt><dd><?= is_https() ? '<span class="badge badge-green">HTTPS (encrypted)</span>' : '<span class="badge badge-amber">HTTP: acceptable for local development only</span>' ?></dd>
        <dt>Environment</dt><dd><?= IS_PRODUCTION ? '<span class="badge badge-green">Production: errors hidden</span>' : '<span class="badge badge-amber">Development: errors shown</span>' ?></dd>
        <dt>Session cookie</dt><dd><span class="badge badge-green">HttpOnly</span> <span class="badge badge-green">SameSite=Lax</span> <?= is_https() ? '<span class="badge badge-green">Secure</span>' : '<span class="badge badge-grey">Secure (needs HTTPS)</span>' ?></dd>
        <dt>Security headers</dt><dd><span class="badge badge-green">CSP</span> <span class="badge badge-green">X-Frame-Options</span> <span class="badge badge-green">nosniff</span> <span class="badge badge-green">Referrer-Policy</span> <?= is_https() ? '<span class="badge badge-green">HSTS</span>' : '' ?></dd>
        <dt>Session timeout</dt><dd><?= SESSION_TIMEOUT / 60 ?> minutes of inactivity</dd>
        <dt>Login lockout</dt><dd><?= MAX_LOGIN_ATTEMPTS ?> failed attempts &rarr; <?= LOCKOUT_MINUTES ?> minute block</dd>
    </dl>
</section>

<section class="card">
    <h2>Security measures implemented</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Measure</th><th>How it is implemented</th><th>Code location</th></tr></thead>
        <tbody>
        <?php foreach ($measures as [$title, $where, $how]): ?>
            <tr><td><strong><?= e($title) ?></strong></td><td><?= $how /* trusted static text written by the developer */ ?></td><td><code><?= e($where) ?></code></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Examples from the code</h2>
    <h3>1. SQL injection: unsafe vs safe</h3>
    <pre><code>// UNSAFE (never used in this project): input becomes part of the SQL
$sql = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";
// Input  ' OR '1'='1  would return every user.

// SAFE (used everywhere): prepared statement with a bound parameter
$stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);</code></pre>

    <h3>2. XSS: escaping output</h3>
    <pre><code>function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// &lt;script&gt;alert(1)&lt;/script&gt; is displayed as harmless text:
echo e($feedback['message']);</code></pre>

    <h3>3. Password hashing</h3>
    <pre><code>$hash = password_hash($password, PASSWORD_DEFAULT);   // at registration
if (password_verify($password, $user['password_hash'])) { ... }   // at login</code></pre>

    <h3>4. Access control on a record</h3>
    <pre><code>if ((int) $fb['user_id'] !== (int) current_user()['id']) {
    audit('access_denied', 'Tried to open feedback owned by another user');
    forbidden();   // HTTP 403
}</code></pre>
</section>

<section class="card">
    <h2>Threats considered (OWASP Top 10 mapping)</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>OWASP risk</th><th>Mitigation in FeedbackHub</th></tr></thead>
        <tbody>
            <tr><td>A01 Broken Access Control</td><td>require_login(), require_admin(), ownership checks, 403 page, access logging</td></tr>
            <tr><td>A02 Cryptographic Failures</td><td>bcrypt password hashing, HTTPS + HSTS in production, Secure cookies</td></tr>
            <tr><td>A03 Injection (SQL, XSS)</td><td>Prepared statements, output escaping, Content-Security-Policy, input validation</td></tr>
            <tr><td>A04 Insecure Design</td><td>Role cannot be chosen at sign-up, admins cannot demote or lock themselves out</td></tr>
            <tr><td>A05 Security Misconfiguration</td><td>.env outside code, .htaccess blocks private folders, errors hidden in production, security headers</td></tr>
            <tr><td>A07 Identification &amp; Authentication Failures</td><td>Strong password rules, login lockout, session regeneration and timeout, generic login error</td></tr>
            <tr><td>A09 Security Logging &amp; Monitoring Failures</td><td>activity_logs table, admin Activity Logs page, storage/logs/app.log</td></tr>
        </tbody>
    </table></div>
    <p class="mt">Each measure is checked by an automated test. See the <a href="<?= e(url('testing.php')) ?>">Testing page</a> for the results.</p>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
