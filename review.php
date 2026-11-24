<?php
require __DIR__ . '/includes/bootstrap.php';

$auto = is_readable(ROOT_PATH . '/tests/results.json') ? json_decode(file_get_contents(ROOT_PATH . '/tests/results.json'), true) : null;
$resp = is_readable(ROOT_PATH . '/tests/responsive.json') ? json_decode(file_get_contents(ROOT_PATH . '/tests/responsive.json'), true) : null;
$counts = db()->query("SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM feedback) AS feedback,
    (SELECT COUNT(*) FROM replies) AS replies, (SELECT COUNT(*) FROM activity_logs) AS logs")->fetch();
$catSummary = [];
foreach ($auto['tests'] ?? [] as $t) {
    $catSummary[$t['category']]['total'] = ($catSummary[$t['category']]['total'] ?? 0) + 1;
    $catSummary[$t['category']]['passed'] = ($catSummary[$t['category']]['passed'] ?? 0) + ($t['pass'] ? 1 : 0);
}

$pageTitle = 'Project review';
require __DIR__ . '/includes/header.php';
?>
<div class="doc">
<div class="page-head"><div><h1>Project review and evaluation</h1><p class="muted">Building Web Applications: Web Application Security, Testing, Deployment and Review. FeedbackHub, by Agu Prince.</p></div></div>

<nav class="toc" aria-label="On this page">
    <a href="#what">What it does</a><a href="#features">Features</a><a href="#security">Security</a><a href="#testing">Testing results</a>
    <a href="#performance">Performance</a><a href="#problems">Problems encountered</a><a href="#limitations">Limitations</a><a href="#future">Future improvements</a>
</nav>

<section class="card" id="what">
    <h2>1. What the application does</h2>
    <p>FeedbackHub is a web-based customer feedback system. Customers create an account, rate their experience from 1 to 5 stars, describe a problem or a compliment, and follow each submission until it is resolved. Administrators see every submission, filter and search them, reply to customers, change their status, manage user accounts, export reports and watch security activity.</p>
    <p>It replaces paper forms and scattered emails with one system, so no complaint is lost and management can see trends such as which category gets the lowest ratings.</p>
    <p><strong>Technology:</strong> PHP 8 (no framework), MySQL/MariaDB, HTML5, CSS3 (responsive, mobile-first), vanilla JavaScript and Chart.js. It runs on XAMPP for development and on any PHP host in production.</p>
</section>

<section class="card" id="features">
    <h2>2. Features implemented</h2>
    <div class="grid grid-2">
        <div>
            <h3>Customer (User role)</h3>
            <ul class="check-list">
                <li>Registration with live password-strength meter</li>
                <li>Login and logout</li>
                <li>Personal dashboard with statistics</li>
                <li>Feedback form: category, subject, star rating, message</li>
                <li>“My feedback” list with search and filters (status, category, rating)</li>
                <li>Feedback detail page with support replies</li>
                <li>Delete own feedback while still pending</li>
                <li>Profile page: edit details, change password</li>
            </ul>
        </div>
        <div>
            <h3>Administrator (Admin role)</h3>
            <ul class="check-list">
                <li>Dashboard with 4 charts and key figures</li>
                <li>All feedback: search, filter, sort and pagination</li>
                <li>Reply to customers and update status (Pending → In Review → Resolved)</li>
                <li>User management: activate/deactivate, change role</li>
                <li>Activity and security log viewer</li>
                <li>CSV report export (opens in Excel)</li>
                <li>Deployment readiness checklist</li>
                <li>Responsive navigation for phone and computer</li>
            </ul>
        </div>
    </div>
    <p class="muted small mt">Current data: <?= (int) $counts['users'] ?> users, <?= (int) $counts['feedback'] ?> feedback records, <?= (int) $counts['replies'] ?> replies, <?= (int) $counts['logs'] ?> logged events.</p>
</section>

<section class="card" id="security">
    <h2>3. Security measures</h2>
    <p>Password hashing (bcrypt), role-based access control, ownership checks (IDOR prevention), server-side input validation, prepared statements against SQL injection, output escaping and Content-Security-Policy against XSS, CSRF tokens, secure session cookies with regeneration and timeout, brute-force lockout, security headers, HTTPS enforcement, secrets in <code>.env</code>, hidden error details in production, and audit logging.</p>
    <p><a href="<?= e(url('security.php')) ?>">Full details are on the Security page &rarr;</a></p>
</section>

<section class="card" id="testing">
    <h2>4. Testing results</h2>
    <?php if ($auto): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Test category</th><th>Tests</th><th>Passed</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($catSummary as $cat => $c): ?>
                <tr><td><?= e($cat) ?></td><td><?= $c['total'] ?></td><td><?= $c['passed'] ?></td><td class="<?= $c['passed'] === $c['total'] ? 'pass' : 'fail' ?>"><?= round($c['passed'] / $c['total'] * 100) ?>%</td></tr>
            <?php endforeach; ?>
            <?php if ($resp): ?>
                <tr><td>Responsive / mobile</td><td><?= (int) $resp['total'] ?></td><td><?= (int) $resp['passed'] ?></td><td class="<?= $resp['passed'] === $resp['total'] ? 'pass' : 'fail' ?>"><?= round($resp['passed'] / max(1, $resp['total']) * 100) ?>%</td></tr>
            <?php endif; ?>
            <tr><td>Usability</td><td colspan="3">Carried out manually with test users (see Testing page)</td></tr>
            </tbody>
        </table></div>
        <p class="mt"><strong><?= (int) $auto['passed'] ?> of <?= (int) $auto['total'] ?></strong> automated tests passed on <?= e(fmt_date($auto['run_at'], true)) ?>. <a href="<?= e(url('testing.php')) ?>">See every test case &rarr;</a></p>
    <?php else: ?>
        <p class="muted">Run the test suite to fill this section (see the Testing page).</p>
    <?php endif; ?>
</section>

<section class="card" id="performance">
    <h2>5. Performance observations</h2>
    <ul>
        <?php if (!empty($auto['performance_ms'])): ?>
            <li>Measured server response times during testing: <?php
                $parts = [];
                foreach ($auto['performance_ms'] as $p => $ms) { $parts[] = e($p) . ' ' . e($ms) . ' ms'; }
                echo implode(', ', $parts); ?>. All pages are well under the common 200 ms target on a local server.</li>
        <?php endif; ?>
        <li>Database indexes on <code>status</code>, <code>created_at</code> and login-attempt columns keep filtering and lockout checks fast as data grows.</li>
        <li>Lists are paginated (10 per page for admins), so a large number of records does not slow pages down.</li>
        <li>No CSS framework is used and Chart.js loads only on the admin dashboard. Pages stay light on mobile data.</li>
        <li>Static files (CSS/JS) are cached by the browser for 7 days through <code>.htaccess</code>.</li>
        <li>Online, response time will be higher because of network distance and shared-hosting load. It should be measured again after deployment.</li>
    </ul>
</section>

<section class="card" id="problems">
    <h2>6. Problems encountered and solutions</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Problem</th><th>Solution</th></tr></thead>
        <tbody>
            <tr><td>The Content-Security-Policy blocked inline JavaScript, which charts normally use</td><td>Moved all scripts into <code>app.js</code> and passed chart data through escaped <code>data-*</code> attributes</td></tr>
            <tr><td>The app lives in a sub-folder on XAMPP (<code>/feedback-system</code>) but at the root on a live domain, so links broke when moved</td><td>The base URL is detected automatically, and all links are built with a <code>url()</code> helper</td></tr>
            <tr><td>The admin dashboard scrolled sideways on phones (found by the responsive test)</td><td>CSS fix: grid columns may now shrink, and tables scroll inside their card</td></tr>
            <tr><td>Rejected CSRF requests showed a 500 error on Apache (XAMPP) but not on the PHP dev server</td><td>Testing on the same server type as production showed it; switched to the standard 403 status</td></tr>
            <tr><td>Brute-force lockout also blocked the tester during repeated test runs</td><td>The test suite clears its own failed-login records after the lockout test</td></tr>
            <tr><td>An admin could accidentally remove their own admin role or deactivate themselves and lose access</td><td>The server refuses changes to your own account on the Users page</td></tr>
            <tr><td>CSV exports could carry spreadsheet formulas (e.g. <code>=HYPERLINK(...)</code>) typed by users</td><td>Cells starting with <code>= + - @</code> are prefixed so Excel treats them as text</td></tr>
        </tbody>
    </table></div>
</section>

<section class="card" id="limitations">
    <h2>7. Limitations</h2>
    <ul>
        <li>There is no “forgot password” email, because the project has no email server. An admin must help users who forget their password.</li>
        <li>Email addresses are not verified at registration.</li>
        <li>No two-factor authentication (2FA) for administrators.</li>
        <li>Customers cannot attach screenshots or files to their feedback.</li>
        <li>Customers and admins are not notified (email or SMS) when a reply is posted; they must log in to check.</li>
        <li>Login throttling is per IP and email. A distributed attack from many IP addresses would need a firewall or CAPTCHA as well.</li>
        <li>Usability testing used a small number of people, so its results are indicative only.</li>
    </ul>
</section>

<section class="card" id="future">
    <h2>8. Possible future improvements</h2>
    <ul>
        <li>Password reset and email verification using an email service such as SMTP or Mailgun.</li>
        <li>Two-factor authentication (authenticator app) for admin accounts.</li>
        <li>Email/SMS notifications when feedback status changes.</li>
        <li>Anonymous feedback option and QR codes in physical shops that link to the form.</li>
        <li>Automatic sentiment analysis to flag angry messages for faster response.</li>
        <li>File/screenshot uploads with type and size checks and virus scanning.</li>
        <li>CAPTCHA (e.g. Cloudflare Turnstile) on registration and login.</li>
        <li>A REST API and mobile app, plus automated deployment (CI/CD) that runs the test suite before every release.</li>
    </ul>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
