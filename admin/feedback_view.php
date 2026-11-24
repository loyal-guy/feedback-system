<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

$load = function () use ($id) {
    $stmt = db()->prepare('SELECT f.*, c.name AS category, u.full_name, u.email, u.phone
        FROM feedback f JOIN categories c ON c.id = f.category_id JOIN users u ON u.id = f.user_id WHERE f.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
};
$fb = $load();
if (!$fb) {
    not_found();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $v = new Validator($_POST);
        $v->required('message', 'Reply')->length('message', 'Reply', 2, 2000)
          ->in('status', 'status', FEEDBACK_STATUSES);
        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            $pdo = db();
            $pdo->beginTransaction();   // reply + status change succeed or fail together
            try {
                $pdo->prepare('INSERT INTO replies (feedback_id, admin_id, message) VALUES (?, ?, ?)')
                    ->execute([$id, current_user()['id'], $v->value('message')]);
                $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ?')->execute([$v->value('status'), $id]);
                $pdo->commit();
            } catch (Throwable $t) {
                $pdo->rollBack();
                throw $t;
            }
            audit('feedback_replied', 'Feedback #' . $id . ' -> ' . $v->value('status'));
            flash('success', 'Reply sent and status updated.');
            redirect('admin/feedback_view.php?id=' . $id);
        }
    } elseif ($action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (in_array($status, FEEDBACK_STATUSES, true)) {
            db()->prepare('UPDATE feedback SET status = ? WHERE id = ?')->execute([$status, $id]);
            audit('feedback_status', 'Feedback #' . $id . ' -> ' . $status);
            flash('success', 'Status changed to ' . $status . '.');
        }
        redirect('admin/feedback_view.php?id=' . $id);
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM feedback WHERE id = ?')->execute([$id]);
        audit('feedback_deleted', 'Admin deleted feedback #' . $id);
        flash('success', 'Feedback #' . $id . ' deleted.');
        redirect('admin/feedback.php');
    }
}

$stmt = db()->prepare('SELECT r.message, r.created_at, u.full_name FROM replies r JOIN users u ON u.id = r.admin_id WHERE r.feedback_id = ? ORDER BY r.created_at');
$stmt->execute([$id]);
$replies = $stmt->fetchAll();

$pageTitle = 'Feedback #' . $id;
require ROOT_PATH . '/includes/header.php';
?>
<p><a href="<?= e(url('admin/feedback.php')) ?>">&larr; Back to all feedback</a></p>
<div class="grid-2-1">
    <article class="card">
        <div class="card-header"><h1><?= e($fb['subject']) ?></h1><?= status_badge($fb['status']) ?></div>
        <dl class="meta-list">
            <dt>Reference</dt><dd>#<?= (int) $fb['id'] ?></dd>
            <dt>Customer</dt><dd><?= e($fb['full_name']) ?> (<?= e($fb['email']) ?><?= $fb['phone'] ? ', ' . e($fb['phone']) : '' ?>)</dd>
            <dt>Category</dt><dd><?= e($fb['category']) ?></dd>
            <dt>Rating</dt><dd><?= stars((int) $fb['rating']) ?> (<?= (int) $fb['rating'] ?>/5)</dd>
            <dt>Submitted</dt><dd><?= e(fmt_date($fb['created_at'], true)) ?></dd>
        </dl>
        <h3 class="mt">Message</h3>
        <div class="message-box"><?= e($fb['message']) ?></div>

        <h3 class="mt">Replies (<?= count($replies) ?>)</h3>
        <?php if (!$replies): ?><p class="muted">No replies yet.</p><?php endif; ?>
        <?php foreach ($replies as $r): ?>
            <div class="reply">
                <div class="reply-meta"><?= e($r['full_name']) ?> &middot; <?= e(fmt_date($r['created_at'], true)) ?></div>
                <?= nl2br(e($r['message'])) ?>
            </div>
        <?php endforeach; ?>
    </article>

    <aside>
        <section class="card">
            <h2>Reply to customer</h2>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reply">
                <div class="form-group <?= isset($errors['message']) ? 'has-error' : '' ?>">
                    <label for="message">Reply</label>
                    <textarea id="message" name="message" maxlength="2000" required><?= e($_POST['message'] ?? '') ?></textarea>
                    <?= field_error($errors, 'message') ?>
                </div>
                <div class="form-group">
                    <label for="status">Set status to</label>
                    <select id="status" name="status">
                        <?php foreach (FEEDBACK_STATUSES as $s): ?>
                            <option <?= ($fb['status'] === 'Pending' ? 'In Review' : $fb['status']) === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'status') ?>
                </div>
                <button type="submit" class="btn">Send reply</button>
            </form>
        </section>
        <section class="card">
            <h2>Quick actions</h2>
            <form method="post" class="btn-row">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <?php foreach (FEEDBACK_STATUSES as $s): if ($s === $fb['status']) continue; ?>
                    <button type="submit" name="status" value="<?= e($s) ?>" class="btn btn-small btn-outline">Mark <?= e($s) ?></button>
                <?php endforeach; ?>
            </form>
            <form method="post" class="mt" data-confirm="Permanently delete this feedback and its replies?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-small btn-danger">Delete feedback</button>
            </form>
        </section>
    </aside>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
