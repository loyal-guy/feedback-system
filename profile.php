<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$user = current_user();

$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $v = new Validator($_POST);
        $v->required('full_name', 'Full name')->length('full_name', 'Full name', 3, 100)->name('full_name', 'Full name')
          ->phone('phone');
        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            db()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
                ->execute([$v->value('full_name'), $v->value('phone') ?: null, $user['id']]);
            audit('profile_updated', 'Profile details changed');
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $v = new Validator($_POST);
        $v->required('current_password', 'Current password')
          ->required('new_password', 'New password')->strongPassword('new_password')
          ->matches('confirm_password', 'new_password', 'New passwords do not match.');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $stmt->fetchColumn())) {
            $v->addError('current_password', 'Current password is incorrect.');
        } elseif (($_POST['new_password'] ?? '') === ($_POST['current_password'] ?? '')) {
            $v->addError('new_password', 'New password must be different from the current one.');
        }

        if ($v->fails()) {
            $pwErrors = $v->errors();
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            audit('password_changed', 'Password changed');
            flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>My profile</h1><p class="muted">Manage your account details and password.</p></div></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Account details</h2>
        <dl class="meta-list">
            <dt>Email</dt><dd><?= e($user['email']) ?></dd>
            <dt>Role</dt><dd><span class="badge <?= $user['role'] === 'admin' ? 'badge-red' : 'badge-grey' ?>"><?= e(ucfirst($user['role'])) ?></span></dd>
            <dt>Member since</dt><dd><?= e(fmt_date($user['created_at'])) ?></dd>
            <dt>Last login</dt><dd><?= e(fmt_date($user['last_login'], true)) ?></dd>
        </dl>
        <form method="post" class="mt" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" maxlength="100" required>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? $user['phone']) ?>" maxlength="15">
                <?= field_error($errors, 'phone') ?>
            </div>
            <p class="hint">Email addresses cannot be changed here, to protect against account takeover.</p>
            <button type="submit" class="btn">Save changes</button>
        </form>
    </section>
    <section class="card">
        <h2>Change password</h2>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="form-group <?= isset($pwErrors['current_password']) ? 'has-error' : '' ?>">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                <?= field_error($pwErrors, 'current_password') ?>
            </div>
            <div class="form-group <?= isset($pwErrors['new_password']) ? 'has-error' : '' ?>">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" maxlength="72" required autocomplete="new-password" data-strength="pw-strength">
                <div class="strength"><div id="pw-strength" class="strength-bar"></div></div>
                <?= field_error($pwErrors, 'new_password') ?>
            </div>
            <div class="form-group <?= isset($pwErrors['confirm_password']) ? 'has-error' : '' ?>">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" maxlength="72" required autocomplete="new-password">
                <?= field_error($pwErrors, 'confirm_password') ?>
            </div>
            <button type="submit" class="btn">Change password</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
