<?php
/** Page header + responsive navigation. Set $pageTitle before including. */
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$inAdmin = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
function nav_link(string $path, string $label, string $current, bool $inAdmin): string
{
    $active = (basename($path) === $current && str_starts_with($path, 'admin/') === $inAdmin) ? ' class="active" aria-current="page"' : '';
    return '<a href="' . e(url($path)) . '"' . $active . '>' . e($label) . '</a>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark">F</span><?= e(APP_NAME) ?></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav">
            <span class="sr-only">Menu</span><span></span><span></span><span></span>
        </button>
        <nav id="main-nav" class="main-nav" aria-label="Main">
            <?php if (!$user): ?>
                <?= nav_link('index.php', 'Home', $current, $inAdmin) ?>
            <?php elseif ($user['role'] === 'admin'): ?>
                <?= nav_link('admin/index.php', 'Dashboard', $current, $inAdmin) ?>
                <?= nav_link('admin/feedback.php', 'All Feedback', $current, $inAdmin) ?>
                <?= nav_link('admin/users.php', 'Users', $current, $inAdmin) ?>
                <?= nav_link('admin/logs.php', 'Activity Logs', $current, $inAdmin) ?>
            <?php else: ?>
                <?= nav_link('dashboard.php', 'Dashboard', $current, $inAdmin) ?>
                <?= nav_link('feedback_new.php', 'Give Feedback', $current, $inAdmin) ?>
                <?= nav_link('my_feedback.php', 'My Feedback', $current, $inAdmin) ?>
            <?php endif; ?>
            <details class="nav-dropdown">
                <summary>Project</summary>
                <div class="dropdown-menu">
                    <?= nav_link('security.php', 'Security', $current, $inAdmin) ?>
                    <?= nav_link('testing.php', 'Testing', $current, $inAdmin) ?>
                    <?= nav_link('deployment.php', 'Deployment', $current, $inAdmin) ?>
                    <?= nav_link('review.php', 'Project Review', $current, $inAdmin) ?>
                </div>
            </details>
            <?php if ($user): ?>
                <?= nav_link('profile.php', 'Profile', $current, $inAdmin) ?>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-small btn-outline">Log out</button>
                </form>
            <?php else: ?>
                <?= nav_link('login.php', 'Log in', $current, $inAdmin) ?>
                <a class="btn btn-small" href="<?= e(url('register.php')) ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main">
<?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
<?php endforeach; ?>
