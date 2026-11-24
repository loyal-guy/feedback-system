<?php
require __DIR__ . '/includes/bootstrap.php';

function read_json(string $file): ?array
{
    $path = ROOT_PATH . '/tests/' . $file;
    return is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
}
$auto = read_json('results.json');
$resp = read_json('responsive.json');
$usab = read_json('usability_tests.json');

$byCat = [];
foreach ($auto['tests'] ?? [] as $t) {
    $byCat[$t['category']][] = $t;
}

$pageTitle = 'Testing';
require __DIR__ . '/includes/header.php';
?>
<div class="doc">
<div class="page-head"><div><h1>Testing</h1><p class="muted">How FeedbackHub was tested, and the recorded results.</p></div></div>

<nav class="toc" aria-label="On this page">
    <a href="#summary">Summary</a><a href="#automated">Automated tests</a><a href="#responsive">Responsive / mobile</a>
    <a href="#usability">Usability</a><a href="#defects">Defects found</a><a href="#how">How to run the tests</a>
</nav>

<section class="grid grid-4" id="summary">
    <div class="stat"><p class="stat-label">Automated tests passed</p><p class="stat-value"><?= $auto ? (int) $auto['passed'] . ' / ' . (int) $auto['total'] : '—' ?></p></div>
    <div class="stat"><p class="stat-label">Responsive checks passed</p><p class="stat-value"><?= $resp ? (int) $resp['passed'] . ' / ' . (int) $resp['total'] : '—' ?></p></div>
    <div class="stat"><p class="stat-label">Test categories</p><p class="stat-value"><?= count($byCat) + 2 ?></p></div>
    <div class="stat"><p class="stat-label">Last automated run</p><p class="stat-value small"><?= $auto ? e(fmt_date($auto['run_at'], true)) : 'Not run yet' ?></p></div>
</section>

<section class="card mt">
    <h2>Testing approach</h2>
    <ul>
        <li><strong>Functional testing:</strong> each feature (submit, search, reply, profile, export) is used end-to-end and the result is checked.</li>
        <li><strong>Login/registration testing:</strong> valid and invalid sign-ups, valid and wrong logins, admin login, logout.</li>
        <li><strong>Form validation testing:</strong> empty fields, wrong formats, weak passwords, values outside the allowed range, tampered dropdown values.</li>
        <li><strong>Security testing:</strong> real attack inputs (SQL injection, XSS, CSRF, IDOR, brute force) are sent to the running application.</li>
        <li><strong>Database testing:</strong> connection, tables, unique, foreign-key and CHECK constraints, and transactions.</li>
        <li><strong>Error handling testing:</strong> missing records, bad parameters and broken business rules show friendly pages, never PHP errors.</li>
        <li><strong>Responsive/mobile testing:</strong> a headless Chrome browser opens each page at phone, tablet and laptop sizes.</li>
        <li><strong>Usability testing:</strong> real people complete common tasks while the tester watches.</li>
    </ul>
</section>

<section class="card" id="automated">
    <h2>Automated test results</h2>
    <?php if (!$auto): ?>
        <div class="alert alert-info">No results yet. Run <code>php tests/run_tests.php http://localhost/feedback-system</code> to generate them.</div>
    <?php else: ?>
        <p class="muted small">Run on <?= e($auto['run_at']) ?> against <code><?= e($auto['target']) ?></code> with PHP <?= e($auto['php']) ?>. Every test sends real HTTP requests or database queries; nothing is simulated.</p>
        <?php foreach ($byCat as $cat => $tests): ?>
            <h3 class="mt"><?= e($cat) ?> testing (<?= count(array_filter($tests, fn($t) => $t['pass'])) ?>/<?= count($tests) ?>)</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Test</th><th>Expected result</th><th>Actual result</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td><?= e($t['id']) ?></td><td><?= e($t['name']) ?></td><td><?= e($t['expected']) ?></td><td><?= e($t['actual']) ?></td>
                        <td class="<?= $t['pass'] ? 'pass' : 'fail' ?>"><?= $t['pass'] ? 'Pass' : 'Fail' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endforeach; ?>

        <h3 class="mt">Performance (server response time)</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Page</th><th>Response time</th></tr></thead>
            <tbody><?php foreach ($auto['performance_ms'] ?? [] as $p => $ms): ?><tr><td><?= e($p) ?></td><td><?= e($ms) ?> ms</td></tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card" id="responsive">
    <h2>Responsive / mobile testing</h2>
    <?php if (!$resp): ?>
        <div class="alert alert-info">Not run yet. See “How to run the tests” below.</div>
    <?php else: ?>
        <p class="muted small">Checked on <?= e($resp['run_at']) ?>: the page must fit the screen with no sideways scrolling, show a menu button on phones and tablets, and open that menu when tapped.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Screen</th><th>Page</th><th>Actual result</th><th>Status</th><th>Screenshot</th></tr></thead>
            <tbody>
            <?php foreach ($resp['results'] as $r): ?>
                <tr>
                    <td class="nowrap"><?= e($r['device']) ?></td><td><?= e($r['page']) ?></td><td><?= e($r['actual']) ?></td>
                    <td class="<?= $r['pass'] ? 'pass' : 'fail' ?>"><?= $r['pass'] ? 'Pass' : 'Fail' ?></td>
                    <td><a href="<?= e(url('assets/img/screenshots/' . basename($r['screenshot']))) ?>" target="_blank" rel="noopener">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card" id="usability">
    <h2>Usability testing</h2>
    <p class="muted small">Usability is tested by watching real people use the system. Results are recorded in <code>tests/usability_tests.json</code>.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Task given to the tester</th><th>Expected result</th><th>Actual result</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($usab['tests'] ?? [] as $u): ?>
            <tr>
                <td><?= e($u['id']) ?></td><td><?= e($u['task']) ?></td><td><?= e($u['expected']) ?></td>
                <td><?= $u['actual'] !== '' ? e($u['actual']) : '<span class="muted">—</span>' ?></td>
                <td><?= $u['status'] === 'Pass' ? '<span class="pass">Pass</span>' : ($u['status'] === 'Fail' ? '<span class="fail">Fail</span>' : '<span class="badge badge-grey">' . e($u['status']) . '</span>') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card" id="defects">
    <h2>Defects found during testing</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Defect</th><th>Found by</th><th>Cause</th><th>Fix</th><th>Status</th></tr></thead>
        <tbody>
            <tr>
                <td>Admin dashboard scrolled sideways on phones (375 px)</td>
                <td>Responsive test (Phone, Admin dashboard)</td>
                <td>Grid columns kept the full width of the “Latest feedback” table instead of letting it scroll inside its card</td>
                <td>Added <code>min-width: 0</code> to grid children in style.css</td>
                <td class="pass">Fixed, re-tested</td>
            </tr>
            <tr>
                <td>Repeated failed logins during testing locked out the tester’s own IP</td>
                <td>Automated test run (brute-force test)</td>
                <td>Lockout counts failures per email <em>and</em> per IP address, as designed</td>
                <td>Test suite clears its own login attempts after the lockout test</td>
                <td class="pass">Fixed</td>
            </tr>
            <tr>
                <td>Rejected CSRF requests returned “500 Internal Server Error” on Apache/XAMPP</td>
                <td>Security tests S5/S6 run against Apache</td>
                <td>The code used the non-standard status 419; Apache does not recognise it and turns it into 500</td>
                <td>Changed to the standard 403 Forbidden and re-ran the tests</td>
                <td class="pass">Fixed, re-tested</td>
            </tr>
        </tbody>
    </table></div>
</section>

<section class="card" id="how">
    <h2>How to run the tests</h2>
    <ol class="steps">
        <li>Start <strong>Apache</strong> and <strong>MySQL</strong> in the XAMPP Control Panel and make sure the site opens at <code>http://localhost/feedback-system</code>.</li>
        <li>Open a terminal in the project folder (XAMPP: click <em>Shell</em>, then <code>cd htdocs/feedback-system</code>).</li>
        <li>Run the automated tests:<pre><code>php tests/run_tests.php http://localhost/feedback-system</code></pre>Results are saved to <code>tests/results.json</code> (shown on this page) and <code>tests/TEST_REPORT.md</code> (for your report).</li>
        <li>Optional responsive test (needs Python):<pre><code>pip install playwright
playwright install chromium
python tests/responsive_test.py http://localhost/feedback-system</code></pre></li>
        <li>Manual check on a real phone: connect the phone to the same Wi-Fi and open <code>http://YOUR-PC-IP/feedback-system</code>. You can also use Chrome DevTools (F12, then the device toolbar).</li>
    </ol>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
