<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (is_admin()) {
    redirect('admin/feedback.php');
}

// Filters are whitelisted; the keyword is passed as a bound parameter (no SQL injection).
$q = get_filter('q');
$status = get_filter('status', FEEDBACK_STATUSES);
$category = get_filter('category', category_ids());
$rating = get_filter('rating', ['1', '2', '3', '4', '5']);

$where = ['f.user_id = ?'];
$params = [current_user()['id']];
if ($q !== '') {
    $where[] = '(f.subject LIKE ? OR f.message LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like);
}
if ($status !== '') { $where[] = 'f.status = ?'; $params[] = $status; }
if ($category !== '') { $where[] = 'f.category_id = ?'; $params[] = (int) $category; }
if ($rating !== '') { $where[] = 'f.rating = ?'; $params[] = (int) $rating; }

$sql = 'SELECT f.id, f.subject, f.rating, f.status, f.created_at, c.name AS category,
    (SELECT COUNT(*) FROM replies r WHERE r.feedback_id = f.id) AS reply_count
    FROM feedback f JOIN categories c ON c.id = f.category_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY f.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'My feedback';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>My feedback</h1><p class="muted"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?></p></div>
    <a class="btn btn-accent" href="<?= e(url('feedback_new.php')) ?>">+ Give feedback</a>
</div>

<div class="card">
    <form method="get" class="filters" role="search">
        <div class="form-group"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Subject or message…"></div>
        <div class="form-group"><label for="status">Status</label>
            <select id="status" name="status" data-autosubmit><option value="">All</option>
                <?php foreach (FEEDBACK_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="category">Category</label>
            <select id="category" name="category" data-autosubmit><option value="">All</option>
                <?php foreach (categories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $category === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="rating">Rating</label>
            <select id="rating" name="rating" data-autosubmit><option value="">All</option>
                <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $rating === (string) $i ? 'selected' : '' ?>><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
            </select></div>
        <div class="btn-row"><button class="btn" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('my_feedback.php')) ?>">Reset</a></div>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">No feedback matches your search.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Rating</th><th>Status</th><th>Replies</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $f): ?>
                <tr>
                    <td><?= (int) $f['id'] ?></td>
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
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
