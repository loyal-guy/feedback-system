<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $v = new Validator($_POST);
    $v->required('full_name', 'Full name')->length('full_name', 'Full name', 3, 100)->name('full_name', 'Full name')
      ->required('email', 'Email')->email('email')
      ->phone('phone')
      ->required('password', 'Password')->strongPassword('password')
      ->matches('password_confirm', 'password', 'Passwords do not match.');

    if (empty($_POST['terms'])) {
        $v->addError('terms', 'You must accept the terms to continue.');
    }

    $old = ['full_name' => $v->value('full_name'), 'email' => strtolower($v->value('email')), 'phone' => $v->value('phone')];

    if (!$v->fails()) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $v->addError('email', 'An account with this email already exists.');
        }
    }

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        // Role is always "user" here; it can never be chosen by the person registering.
        $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$old['full_name'], $old['email'], $old['phone'] ?: null,
            password_hash($_POST['password'], PASSWORD_DEFAULT), 'user']);
        audit('register', 'New account: ' . $old['email']);
        flash('success', 'Account created successfully. You can now log in.');
        redirect('login.php');
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="card form-narrow">
    <h1>Create your account</h1>
    <p class="muted">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    <?php if ($errors): ?><div class="alert alert-error" role="alert">Please correct the errors below.</div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required autocomplete="name">
            <?= field_error($errors, 'full_name') ?>
        </div>
        <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" maxlength="150" required autocomplete="email">
            <?= field_error($errors, 'email') ?>
        </div>
        <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Phone number <span class="muted">(optional)</span></label>
            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="15" placeholder="08012345678" autocomplete="tel">
            <?= field_error($errors, 'phone') ?>
        </div>
        <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
            <label for="password">Password <button type="button" class="btn btn-small btn-outline" data-toggle-password="password">Show</button></label>
            <input type="password" id="password" name="password" maxlength="72" required autocomplete="new-password" data-strength="strength-bar">
            <div class="strength"><div id="strength-bar" class="strength-bar"></div></div>
            <p class="hint">At least 8 characters with upper case, lower case, a number and a symbol.</p>
            <?= field_error($errors, 'password') ?>
        </div>
        <div class="form-group <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" maxlength="72" required autocomplete="new-password">
            <?= field_error($errors, 'password_confirm') ?>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="terms" value="1"> I agree that my feedback may be used to improve services.</label>
            <?= field_error($errors, 'terms') ?>
        </div>
        <button type="submit" class="btn">Create account</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
