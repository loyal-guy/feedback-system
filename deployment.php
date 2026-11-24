<?php
require __DIR__ . '/includes/bootstrap.php';

// Pre-deployment readiness checks. Details are shown to admins only,
// because configuration information is useful to attackers.
$checks = [];
if (is_admin()) {
    $dbOk = true;
    try { db()->query('SELECT 1'); } catch (Throwable $t) { $dbOk = false; }
    $adminDefault = false;
    $s = db()->prepare("SELECT password_hash FROM users WHERE email = 'admin@feedbackhub.test'");
    $s->execute();
    if ($h = $s->fetchColumn()) { $adminDefault = password_verify('Admin@123', $h); }

    $checks = [
        ['PHP version 8.1 or newer', version_compare(PHP_VERSION, '8.1', '>='), 'PHP ' . PHP_VERSION],
        ['Database connection', $dbOk, $dbOk ? 'Connected' : 'Failed'],
        ['.env file present', is_readable(ROOT_PATH . '/.env'), is_readable(ROOT_PATH . '/.env') ? 'Found' : 'Missing: copy .env.example to .env'],
        ['APP_ENV set to production', IS_PRODUCTION, 'Currently: ' . APP_ENV],
        ['Error details hidden from users', ini_get('display_errors') === '0' || ini_get('display_errors') === '', 'display_errors = ' . (ini_get('display_errors') ?: '0')],
        ['HTTPS enabled', is_https(), is_https() ? 'Secure connection' : 'Not using HTTPS'],
        ['FORCE_HTTPS enabled', FORCE_HTTPS, FORCE_HTTPS ? 'On' : 'Off'],
        ['Log folder writable', is_writable(ROOT_PATH . '/storage/logs'), 'storage/logs'],
        ['Backup folder writable', is_writable(ROOT_PATH . '/storage/backups'), 'storage/backups'],
        ['Demo admin password changed', !$adminDefault, $adminDefault ? 'Still using Admin@123: change it!' : 'Changed'],
    ];
}

$pageTitle = 'Deployment';
require __DIR__ . '/includes/header.php';
?>
<div class="doc">
<div class="page-head"><div><h1>Deployment</h1><p class="muted">How FeedbackHub moves from the developer’s computer (XAMPP) to a live server on the internet.</p></div></div>

<nav class="toc" aria-label="On this page">
    <a href="#readiness">Readiness check</a><a href="#environments">Environments</a><a href="#database">Database</a>
    <a href="#env">Environment variables</a><a href="#server">Server configuration</a><a href="#hosting">Domain &amp; hosting</a>
    <a href="#https">HTTPS / SSL</a><a href="#process">Deployment process</a><a href="#backup">Backups</a><a href="#monitoring">Logs &amp; monitoring</a>
</nav>

<section class="card" id="readiness">
    <h2>Deployment readiness check</h2>
    <?php if (!is_admin()): ?>
        <p class="muted">An administrator can see a live checklist here that tests this server’s configuration before going live. It is hidden from other visitors because configuration details help attackers.</p>
    <?php else: ?>
        <p class="muted small">Live results for this server. Some items are expected to fail on a local XAMPP development machine.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($checks as [$name, $ok, $detail]): ?>
                <tr><td><?= e($name) ?></td><td><?= e($detail) ?></td><td><?= $ok ? '<span class="pass">OK</span>' : '<span class="badge badge-amber">Action needed</span>' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card" id="environments">
    <h2>Development vs production</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Setting</th><th>Development (XAMPP)</th><th>Production (live)</th></tr></thead>
        <tbody>
            <tr><td>Address</td><td><code>http://localhost/feedback-system</code></td><td><code>https://feedbackhub.yourdomain.com</code></td></tr>
            <tr><td>APP_ENV</td><td>development</td><td>production</td></tr>
            <tr><td>Error messages</td><td>Shown on screen to help debugging</td><td>Hidden; written to <code>storage/logs/app.log</code></td></tr>
            <tr><td>HTTPS</td><td>Not needed (local only)</td><td>Required; <code>FORCE_HTTPS=true</code></td></tr>
            <tr><td>Database user</td><td>root (no password)</td><td>Dedicated user with a strong password and access to one database only</td></tr>
            <tr><td>Demo accounts</td><td>Shown on the login page</td><td>Hidden; admin password changed</td></tr>
        </tbody>
    </table></div>
</section>

<section class="card" id="database">
    <h2>Database configuration</h2>
    <ol class="steps">
        <li>Create the database and import <code>database/schema.sql</code> (phpMyAdmin → Import, or <code>mysql -u root -p &lt; database/schema.sql</code>).</li>
        <li>On the live server, create a dedicated database user that can only use this database:
<pre><code>CREATE USER 'feedback_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON feedback_system.* TO 'feedback_app'@'localhost';
FLUSH PRIVILEGES;</code></pre>
            The application never uses <code>root</code> in production. It cannot drop tables or reach other databases (principle of least privilege).</li>
        <li>Put the database name, user and password in <code>.env</code>, not in the PHP code.</li>
    </ol>
</section>

<section class="card" id="env">
    <h2>Environment variables (.env)</h2>
    <p>Settings that change between computers, and secrets, are kept in a <code>.env</code> file. <code>.env.example</code> shows which settings exist; the real <code>.env</code> is never shared or uploaded to GitHub, and <code>.htaccess</code> blocks it from the web.</p>
<pre><code>APP_ENV=production
APP_NAME=FeedbackHub
DB_HOST=localhost
DB_NAME=feedback_system
DB_USER=feedback_app
DB_PASS=a-long-random-password
SESSION_TIMEOUT_MINUTES=30
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_MINUTES=15
FORCE_HTTPS=true</code></pre>
</section>

<section class="card" id="server">
    <h2>Server configuration</h2>
    <ul>
        <li><strong>Requirements:</strong> Apache 2.4 (or Nginx), PHP 8.1+ with the <code>pdo_mysql</code> extension, MySQL 8 or MariaDB 10.4+.</li>
        <li><strong>.htaccess</strong> (included) turns off directory listing, blocks private folders (<code>config, includes, database, storage, tests, deploy</code>) and hidden files such as <code>.env</code>, and can force HTTPS.</li>
        <li><strong>VPS option:</strong> <code>deploy/apache-vhost.conf</code> and <code>deploy/nginx.conf</code> are ready-made server configurations.</li>
        <li><strong>File permissions:</strong> code files read-only (644), folders 755, and only <code>storage/</code> writable by the web server.</li>
        <li><strong>PHP settings for production:</strong> <code>display_errors=Off</code>, <code>expose_php=Off</code>, <code>session.cookie_secure=1</code>.</li>
    </ul>
</section>

<section class="card" id="hosting">
    <h2>Domain and hosting</h2>
    <div class="grid grid-3">
        <div><h3>Free hosting (for demonstration)</h3><p class="muted">InfinityFree or AwardSpace provide PHP + MySQL, a free subdomain and free SSL. Good enough for a project defence.</p></div>
        <div><h3>Shared hosting (cPanel)</h3><p class="muted">Paid hosts such as Whogohost, Truehost or Namecheap. Upload with File Manager or FTP, create the database in “MySQL Databases”, and enable AutoSSL.</p></div>
        <div><h3>VPS (full control)</h3><p class="muted">DigitalOcean or AWS Lightsail running Ubuntu with Apache, PHP and MySQL. Most work, but most control over security.</p></div>
    </div>
    <p class="mt">Buy a domain (for example a <code>.com.ng</code> or <code>.com</code> name) and point its <strong>A record</strong> to the server’s IP address, or point its nameservers to the hosting company. DNS changes can take a few hours to spread.</p>
</section>

<section class="card" id="https">
    <h2>HTTPS / SSL</h2>
    <p>HTTPS encrypts everything between the browser and the server, including passwords and session cookies. Without it, anyone on the same Wi-Fi could read them.</p>
    <ul>
        <li><strong>cPanel hosting:</strong> enable <em>AutoSSL</em> or <em>Let’s Encrypt</em> in the SSL/TLS section. It is free and renews automatically.</li>
        <li><strong>VPS:</strong> <code>sudo apt install certbot python3-certbot-apache</code> then <code>sudo certbot --apache -d feedbackhub.yourdomain.com</code>.</li>
        <li>Then set <code>FORCE_HTTPS=true</code> in <code>.env</code>. The app redirects HTTP to HTTPS, marks session cookies <code>Secure</code> and sends the <code>Strict-Transport-Security</code> (HSTS) header.</li>
        <li>Verify the result at <em>ssllabs.com/ssltest</em> (aim for grade A) and <em>securityheaders.com</em>.</li>
    </ul>
</section>

<section class="card" id="process">
    <h2>Deployment process</h2>
    <ol class="steps">
        <li><strong>Test locally:</strong> run <code>php tests/run_tests.php</code>. Deploy only when every test passes.</li>
        <li><strong>Back up</strong> the live database before changing anything (see Backups below).</li>
        <li><strong>Upload the code</strong> by FTP/File Manager (shared hosting) or <code>git pull</code> (VPS). Do not upload <code>.env</code>, <code>tests/</code> results or <code>storage/logs</code>.</li>
        <li><strong>Create the database</strong> and import <code>database/schema.sql</code> on the first deployment only.</li>
        <li><strong>Create .env</strong> on the server from <code>.env.example</code> with production values (<code>APP_ENV=production</code>, the real database password, <code>FORCE_HTTPS=true</code>).</li>
        <li><strong>Set permissions:</strong> make <code>storage/</code> writable and everything else read-only.</li>
        <li><strong>Secure the accounts:</strong> log in as admin, change the demo password, and delete or deactivate the demo users.</li>
        <li><strong>Smoke test the live site:</strong> register, log in, submit feedback, reply as admin. Confirm the padlock shows and that <code>/.env</code> returns “Not Found”.</li>
        <li><strong>Monitor:</strong> check the Activity Logs page and <code>storage/logs/app.log</code> for the first few days.</li>
    </ol>
    <p class="muted small">A full step-by-step guide is in <code>docs/DEPLOYMENT.md</code>.</p>
</section>

<section class="card" id="backup">
    <h2>Backup considerations</h2>
    <ul>
        <li><strong>What:</strong> the database holds all users and feedback, so it is backed up daily. Code is already safe in Git or in the project ZIP.</li>
        <li><strong>How:</strong> <code>deploy/backup.sh</code> (Linux) or <code>deploy/backup.bat</code> (Windows/XAMPP) runs <code>mysqldump</code> and saves a dated, compressed file in <code>storage/backups/</code>, deleting backups older than 14 days.</li>
        <li><strong>When:</strong> automatically every night (cron on Linux: <code>0 2 * * * /var/www/feedback-system/deploy/backup.sh</code>, or Task Scheduler on Windows), and by hand before every deployment.</li>
        <li><strong>Where:</strong> keep at least one copy off the server (Google Drive, or another machine), so a server failure does not destroy the backups too (3-2-1 rule).</li>
        <li><strong>Test restores:</strong> a backup is only useful if it restores. Restore into a test database regularly: <code>mysql -u root -p feedback_test &lt; backup.sql</code>.</li>
    </ul>
</section>

<section class="card" id="monitoring">
    <h2>Error and log monitoring</h2>
    <ul>
        <li><strong>Application log:</strong> <code>storage/logs/app.log</code> records PHP errors, database failures and security events with time and IP address. Users only see a friendly error page.</li>
        <li><strong>Activity log:</strong> the <code>activity_logs</code> table records logins, failed logins, lockouts, denied access and admin actions. Admins review it on the <em>Activity Logs</em> page.</li>
        <li><strong>Dashboard alerts:</strong> the admin dashboard shows failed logins and blocked requests from the last 24 hours.</li>
        <li><strong>Uptime monitoring:</strong> a free service such as UptimeRobot checks the site every 5 minutes and sends an email if it goes down.</li>
        <li><strong>Server logs:</strong> Apache’s <code>access.log</code> and <code>error.log</code> (in cPanel: <em>Metrics → Errors</em>).</li>
    </ul>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
