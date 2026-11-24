<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}
if (is_admin()) {
    redirect('admin/feedback_view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT f.*, c.name AS category FROM feedback f JOIN categories c ON c.id = f.category_id WHERE f.id = ?');
$stmt->execute([$id]);
$fb = $stmt->fetch();
if (!$fb) {
    not_found();
}

// Access-control check (prevents IDOR): a user may only open their OWN feedback,
// even if they change the id in the address bar.
if ((int) $fb['user_id'] !== (int) current_user()['id']) {
    audit('access_denied', 'Tried to open feedback #' . $id . ' owned by another user');
    forbidden();
}

// Delete own feedback (only while still Pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    if ($fb['status'] !== 'Pending') {
        flash('error', 'Only pending feedback can be deleted.');
    } else {
        db()->prepare('DELETE FROM feedback WHERE id = ? AND user_id = ?')->execute([$id, current_user()['id']]);
        audit('feedback_deleted', 'Feedback #' . $id);
        flash('success', 'Feedback deleted.');
        redirect('my_feedback.php');
    }
    redirect('feedback_view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT r.message, r.created_at, u.full_name FROM replies r JOIN users u ON u.id = r.admin_id WHERE r.feedback_id = ? ORDER BY r.created_at');
$stmt->execute([$id]);
$replies = $stmt->fetchAll();

$pageTitle = 'Feedback #' . $id;
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('my_feedback.php')) ?>">&larr; Back to my feedback</a></p>
<div class="grid grid-2-1">
<article class="card">
    <div class="card-header">
        <h1><?= e($fb['subject']) ?></h1>
        <?= status_badge($fb['status']) ?>
    </div>
    <dl class="meta-list">
        <dt>Reference</dt><dd>#<?= (int) $fb['id'] ?></dd>
        <dt>Category</dt><dd><?= e($fb['category']) ?></dd>
        <dt>Rating</dt><dd><?= stars((int) $fb['rating']) ?> (<?= (int) $fb['rating'] ?>/5)</dd>
        <dt>Submitted</dt><dd><?= e(fmt_date($fb['created_at'], true)) ?></dd>
        <dt>Last update</dt><dd><?= e(fmt_date($fb['updated_at'], true)) ?></dd>
    </dl>
    <h3 class="mt">Your message</h3>
    <div class="message-box"><?= e($fb['message']) ?></div>

    <?php if ($fb['status'] === 'Pending'): ?>
        <form method="post" class="mt" data-confirm="Delete this feedback? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $fb['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-small btn-danger">Delete feedback</button>
        </form>
    <?php endif; ?>
</article>

<section class="card">
    <h2>Responses from support</h2>
    <?php if (!$replies): ?>
        <p class="muted">No response yet. We will reply as soon as possible.</p>
    <?php endif; ?>
    <?php foreach ($replies as $r): ?>
        <div class="reply">
            <div class="reply-meta"><?= e($r['full_name']) ?> &middot; <?= e(fmt_date($r['created_at'], true)) ?></div>
            <div class="message-box-plain"><?= nl2br(e($r['message'])) ?></div>
        </div>
    <?php endforeach; ?>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
