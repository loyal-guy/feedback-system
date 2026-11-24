<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (is_admin()) {
    redirect('admin/index.php');
}
$user = current_user();

$stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(status='Pending') AS pending,
    SUM(status='In Review') AS in_review, SUM(status='Resolved') AS resolved FROM feedback WHERE user_id = ?");
$stmt->execute([$user['id']]);
$s = $stmt->fetch();

$stmt = db()->prepare('SELECT f.id, f.subject, f.rating, f.status, f.created_at, c.name AS category,
    (SELECT COUNT(*) FROM replies r WHERE r.feedback_id = f.id) AS reply_count
    FROM feedback f JOIN categories c ON c.id = f.category_id
    WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hello, <?= e($user['full_name']) ?></h1>
        <p class="muted">Last login: <?= e(fmt_date($user['last_login'], true)) ?></p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('feedback_new.php')) ?>">+ Give feedback</a>
</div>

<section class="grid grid-4">
    <div class="stat"><p class="stat-label">Total submitted</p><p class="stat-value"><?= (int) $s['total'] ?></p></div>
    <div class="stat"><p class="stat-label">Pending</p><p class="stat-value"><?= (int) $s['pending'] ?></p></div>
    <div class="stat"><p class="stat-label">In review</p><p class="stat-value"><?= (int) $s['in_review'] ?></p></div>
    <div class="stat"><p class="stat-label">Resolved</p><p class="stat-value"><?= (int) $s['resolved'] ?></p></div>
</section>

<section class="card mt">
    <div class="card-header">
        <h2>Recent feedback</h2>
        <a href="<?= e(url('my_feedback.php')) ?>">View all &rarr;</a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty">You have not submitted any feedback yet. <a href="<?= e(url('feedback_new.php')) ?>">Submit your first one</a>.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Subject</th><th>Category</th><th>Rating</th><th>Status</th><th>Replies</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $f): ?>
                <tr>
                    <td><a href="<?= e(url('feedback_view.php?id=' . (int) $f['id'])) ?>"><?= e($f['subject']) ?></a></td>
                    <td><?= e($f['category']) ?></td>
                    <td><?= stars((int) $f['rating']) ?></td>
                    <td><?= status_badge($f['status']) ?></td>
                    <td><?= (int) $f['reply_count'] ?></td>
                    <td class="nowrap"><?= e(fmt_date($f['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
