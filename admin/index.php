<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();   // Role-based access: only admins get past this line

$t = db()->query("SELECT COUNT(*) AS total, ROUND(AVG(rating),2) AS avg_rating,
    SUM(status='Pending') AS pending, SUM(status='In Review') AS in_review, SUM(status='Resolved') AS resolved
    FROM feedback")->fetch();
$users = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$failed24 = (int) db()->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('login_failed','login_locked') AND created_at > NOW() - INTERVAL 1 DAY")->fetchColumn();
$denied24 = (int) db()->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('access_denied','csrf_rejected') AND created_at > NOW() - INTERVAL 1 DAY")->fetchColumn();

// Chart data: last 6 months
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
foreach (db()->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n FROM feedback
    WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY ym") as $r) {
    if (isset($months[$r['ym']])) {
        $months[$r['ym']] = (int) $r['n'];
    }
}
$monthLabels = array_map(fn($ym) => date('M Y', strtotime($ym . '-01')), array_keys($months));

$byCat = db()->query('SELECT c.name, COUNT(f.id) AS n, ROUND(AVG(f.rating),1) AS avg_r FROM categories c
    LEFT JOIN feedback f ON f.category_id = c.id GROUP BY c.id, c.name ORDER BY n DESC')->fetchAll();

$ratingDist = array_fill(1, 5, 0);
foreach (db()->query('SELECT rating, COUNT(*) AS n FROM feedback GROUP BY rating') as $r) {
    $ratingDist[(int) $r['rating']] = (int) $r['n'];
}

$latest = db()->query("SELECT f.id, f.subject, f.rating, f.status, f.created_at, u.full_name
    FROM feedback f JOIN users u ON u.id = f.user_id ORDER BY f.created_at DESC LIMIT 6")->fetchAll();

function json_attr(array $data): string
{
    return e(json_encode(array_values($data), JSON_UNESCAPED_UNICODE));
}

$pageTitle = 'Admin dashboard';
$extraScripts = ['assets/js/chart.umd.min.js'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Admin dashboard</h1><p class="muted">Overview of customer feedback and security activity.</p></div>
    <div class="btn-row">
        <a class="btn btn-outline" href="<?= e(url('admin/export.php')) ?>">Export CSV report</a>
        <a class="btn" href="<?= e(url('admin/feedback.php?status=Pending')) ?>">Review pending (<?= (int) $t['pending'] ?>)</a>
    </div>
</div>

<section class="grid grid-4">
    <div class="stat"><p class="stat-label">Total feedback</p><p class="stat-value"><?= (int) $t['total'] ?></p></div>
    <div class="stat"><p class="stat-label">Average rating</p><p class="stat-value"><?= e($t['avg_rating'] ?? '0') ?></p></div>
    <div class="stat"><p class="stat-label">Resolution rate</p><p class="stat-value"><?= $t['total'] ? round($t['resolved'] / $t['total'] * 100) : 0 ?>%</p></div>
    <div class="stat"><p class="stat-label">Registered customers</p><p class="stat-value"><?= $users ?></p></div>
</section>

<section class="grid grid-2 mt">
    <div class="card chart-card">
        <h2>Feedback over the last 6 months</h2>
        <div class="chart-box"><canvas data-chart="line" data-label="Feedback" data-labels="<?= json_attr($monthLabels) ?>" data-values="<?= json_attr($months) ?>" role="img" aria-label="Line chart of feedback per month"></canvas></div>
    </div>
    <div class="card chart-card">
        <h2>Status breakdown</h2>
        <div class="chart-box"><canvas data-chart="doughnut" data-labels="<?= json_attr(FEEDBACK_STATUSES) ?>" data-values="<?= json_attr([(int) $t['pending'], (int) $t['in_review'], (int) $t['resolved']]) ?>" role="img" aria-label="Doughnut chart of feedback status"></canvas></div>
    </div>
    <div class="card chart-card">
        <h2>Feedback by category</h2>
        <div class="chart-box"><canvas data-chart="bar" data-label="Feedback" data-labels="<?= json_attr(array_column($byCat, 'name')) ?>" data-values="<?= json_attr(array_map('intval', array_column($byCat, 'n'))) ?>" role="img" aria-label="Bar chart of feedback per category"></canvas></div>
    </div>
    <div class="card chart-card">
        <h2>Rating distribution</h2>
        <div class="chart-box"><canvas data-chart="bar" data-color="#f2a541" data-label="Feedback" data-labels="<?= json_attr(['1 ★', '2 ★', '3 ★', '4 ★', '5 ★']) ?>" data-values="<?= json_attr($ratingDist) ?>" role="img" aria-label="Bar chart of ratings"></canvas></div>
    </div>
</section>

<section class="grid grid-2-1">
    <div class="card">
        <div class="card-header"><h2>Latest feedback</h2><a href="<?= e(url('admin/feedback.php')) ?>">View all &rarr;</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Subject</th><th>Customer</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $f): ?>
                <tr>
                    <td><a href="<?= e(url('admin/feedback_view.php?id=' . (int) $f['id'])) ?>"><?= e($f['subject']) ?></a><br><span class="muted small"><?= e(fmt_date($f['created_at'])) ?></span></td>
                    <td><?= e($f['full_name']) ?></td>
                    <td><?= stars((int) $f['rating']) ?></td>
                    <td><?= status_badge($f['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <h2>Security monitor (24 h)</h2>
        <dl class="meta-list">
            <dt>Failed logins</dt><dd><span class="badge <?= $failed24 ? 'badge-amber' : 'badge-green' ?>"><?= $failed24 ?></span></dd>
            <dt>Blocked requests</dt><dd><span class="badge <?= $denied24 ? 'badge-red' : 'badge-green' ?>"><?= $denied24 ?></span></dd>
            <dt>Environment</dt><dd><?= e(APP_ENV) ?></dd>
            <dt>HTTPS</dt><dd><?= is_https() ? '<span class="badge badge-green">On</span>' : '<span class="badge badge-amber">Off (local)</span>' ?></dd>
        </dl>
        <p class="mt"><a href="<?= e(url('admin/logs.php')) ?>">Open activity logs &rarr;</a></p>
        <h3 class="mt">Average rating by category</h3>
        <dl class="meta-list">
            <?php foreach ($byCat as $c): ?><dt><?= e($c['name']) ?></dt><dd><?= $c['avg_r'] !== null ? e($c['avg_r']) . ' / 5' : '—' ?></dd><?php endforeach; ?>
        </dl>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
