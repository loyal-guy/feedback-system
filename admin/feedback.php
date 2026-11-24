<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$q = get_filter('q');
$status = get_filter('status', FEEDBACK_STATUSES);
$category = get_filter('category', category_ids());
$rating = get_filter('rating', ['1', '2', '3', '4', '5']);
$sort = get_filter('sort', ['newest', 'oldest', 'rating_low', 'rating_high']) ?: 'newest';
$orderBy = match ($sort) {          // whitelisted ORDER BY: user input never goes into SQL directly
    'oldest' => 'f.created_at ASC',
    'rating_low' => 'f.rating ASC, f.created_at DESC',
    'rating_high' => 'f.rating DESC, f.created_at DESC',
    default => 'f.created_at DESC',
};

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(f.subject LIKE ? OR f.message LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') { $where[] = 'f.status = ?'; $params[] = $status; }
if ($category !== '') { $where[] = 'f.category_id = ?'; $params[] = (int) $category; }
if ($rating !== '') { $where[] = 'f.rating = ?'; $params[] = (int) $rating; }
$whereSql = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM feedback f JOIN users u ON u.id = f.user_id WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();

$perPage = 10;
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) (filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1)));
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT f.id, f.subject, f.rating, f.status, f.created_at, c.name AS category, u.full_name, u.email
    FROM feedback f JOIN users u ON u.id = f.user_id JOIN categories c ON c.id = f.category_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'All feedback';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>All feedback</h1><p class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></p></div>
    <a class="btn btn-outline" href="<?= e(url('admin/export.php' . query_with(['page' => null]))) ?>">Export these results (CSV)</a>
</div>
<div class="card">
    <form method="get" class="filters filters-admin" role="search">
        <div class="form-group"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Subject, message, customer or email…"></div>
        <div class="form-group"><label for="status">Status</label>
            <select id="status" name="status" data-autosubmit><option value="">All</option>
                <?php foreach (FEEDBACK_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="category">Category</label>
            <select id="category" name="category" data-autosubmit><option value="">All</option>
                <?php foreach (categories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $category === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'rating_low' => 'Lowest rating', 'rating_high' => 'Highest rating'] as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="btn-row"><button class="btn" type="submit">Filter</button><a class="btn btn-outline" href="<?= e(url('admin/feedback.php')) ?>">Reset</a></div>
        <?php if ($rating !== ''): ?><input type="hidden" name="rating" value="<?= e($rating) ?>"><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">No feedback matches these filters.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Subject</th><th>Customer</th><th>Category</th><th>Rating</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $f): ?>
                <tr>
                    <td><?= (int) $f['id'] ?></td>
                    <td><?= e(excerpt($f['subject'], 60)) ?></td>
                    <td><?= e($f['full_name']) ?><br><span class="muted small"><?= e($f['email']) ?></span></td>
                    <td><?= e($f['category']) ?></td>
                    <td><?= stars((int) $f['rating']) ?></td>
                    <td><?= status_badge($f['status']) ?></td>
                    <td class="nowrap"><?= e(fmt_date($f['created_at'])) ?></td>
                    <td><a class="btn btn-small btn-outline" href="<?= e(url('admin/feedback_view.php?id=' . (int) $f['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= pagination($page, $pages) ?>
    <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
