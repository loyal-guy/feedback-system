<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$action = get_filter('action', $actions);
$securityOnly = get_filter('security', ['1']) === '1';

$where = ['1=1'];
$params = [];
if ($action !== '') { $where[] = 'l.action = ?'; $params[] = $action; }
if ($securityOnly) { $where[] = "l.action IN ('login_failed','login_locked','access_denied','csrf_rejected')"; }

$stmt = db()->prepare('SELECT l.*, u.full_name, u.email FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC, l.id DESC LIMIT 200');
$stmt->execute($params);
$logs = $stmt->fetchAll();

$securityActions = ['login_failed', 'login_locked', 'access_denied', 'csrf_rejected'];
$pageTitle = 'Activity logs';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1>Activity &amp; security logs</h1><p class="muted">Latest 200 events. Errors are also written to <code>storage/logs/app.log</code>.</p></div></div>
<div class="card">
    <form method="get" class="filters filters-3">
        <div class="form-group"><label for="action">Event type</label>
            <select id="action" name="action" data-autosubmit><option value="">All events</option>
                <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label><input type="checkbox" name="security" value="1" <?= $securityOnly ? 'checked' : '' ?>> Security events only</label></div>
        <div class="btn-row"><button class="btn" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('admin/logs.php')) ?>">Reset</a></div>
    </form>
    <?php if (!$logs): ?><div class="empty">No events recorded.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Event</th><th>User</th><th>Details</th><th>IP address</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td class="nowrap"><?= e(fmt_date($l['created_at'], true)) ?></td>
                <td><span class="badge <?= in_array($l['action'], $securityActions, true) ? 'badge-red' : 'badge-grey' ?>"><?= e($l['action']) ?></span></td>
                <td><?= $l['full_name'] ? e($l['full_name']) : '<span class="muted">Guest</span>' ?></td>
                <td><?= e($l['details']) ?></td>
                <td class="nowrap"><?= e($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
