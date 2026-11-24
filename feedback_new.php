<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (is_admin()) {
    flash('info', 'Administrators manage feedback; they do not submit it.');
    redirect('admin/index.php');
}

$errors = [];
$old = ['category_id' => '', 'subject' => '', 'rating' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $v = new Validator($_POST);
    $v->required('category_id', 'Category')->in('category_id', 'category', category_ids())
      ->required('subject', 'Subject')->length('subject', 'Subject', 5, 150)
      ->required('rating', 'Rating')->integerBetween('rating', 'Rating', 1, 5)
      ->required('message', 'Message')->length('message', 'Message', 10, 2000);

    foreach ($old as $k => $_) {
        $old[$k] = $v->value($k);
    }

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        // Stored exactly as typed; it is escaped with e() whenever it is displayed (XSS protection).
        $stmt = db()->prepare('INSERT INTO feedback (user_id, category_id, subject, rating, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([current_user()['id'], (int) $old['category_id'], $old['subject'], (int) $old['rating'], $old['message']]);
        $id = (int) db()->lastInsertId();
        audit('feedback_created', 'Feedback #' . $id);
        flash('success', 'Thank you! Your feedback has been submitted.');
        redirect('feedback_view.php?id=' . $id);
    }
}

$pageTitle = 'Give feedback';
require __DIR__ . '/includes/header.php';
?>
<div class="card form">
    <h1>Give feedback</h1>
    <p class="muted">Tell us about your experience. All fields are required.</p>
    <?php if ($errors): ?><div class="alert alert-error" role="alert">Please correct the errors below.</div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-group <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">— Select a category —</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $old['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </div>
        <div class="form-group <?= isset($errors['subject']) ? 'has-error' : '' ?>">
            <label for="subject">Subject <span class="char-count" id="subject-count"></span></label>
            <input type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>" maxlength="150" required data-counter="subject-count">
            <?= field_error($errors, 'subject') ?>
        </div>
        <div class="form-group <?= isset($errors['rating']) ? 'has-error' : '' ?>">
            <span class="label-like"><strong>Rating</strong></span><br>
            <div class="rating-input" role="radiogroup" aria-label="Rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $old['rating'] === (string) $i ? 'checked' : '' ?>>
                    <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                <?php endfor; ?>
            </div>
            <?= field_error($errors, 'rating') ?>
        </div>
        <div class="form-group <?= isset($errors['message']) ? 'has-error' : '' ?>">
            <label for="message">Message <span class="char-count" id="message-count"></span></label>
            <textarea id="message" name="message" maxlength="2000" required data-counter="message-count"><?= e($old['message']) ?></textarea>
            <?= field_error($errors, 'message') ?>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">Submit feedback</button>
            <a class="btn btn-outline" href="<?= e(url('dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
