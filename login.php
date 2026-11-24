<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (too_many_attempts($email)) {
        $error = 'Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.';
        audit('login_locked', 'Locked out: ' . $email);
    } else {
        // Prepared statement: the email can never change the SQL query (no SQL injection).
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account has been deactivated. Contact the administrator.';
                audit('login_failed', 'Deactivated account: ' . $email);
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                clear_failed_logins($email);
                login_user($user);
                audit('login_success', $email);
                flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
            }
        } else {
            record_failed_login($email);
            audit('login_failed', 'Wrong email or password for: ' . $email);
            // Same message for unknown email and wrong password: attackers cannot discover valid accounts.
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="card form-narrow">
    <h1>Log in</h1>
    <p class="muted">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password <button type="button" class="btn btn-small btn-outline" data-toggle-password="password">Show</button></label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Log in</button>
    </form>
    <?php if (!IS_PRODUCTION): ?>
        <div class="alert alert-info small mt">
            <strong>Demo accounts (development mode only):</strong><br>
            Admin: admin@feedbackhub.test / Admin@123<br>
            User: demo@feedbackhub.test / User@1234
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
