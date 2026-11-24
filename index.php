<?php
require __DIR__ . '/includes/bootstrap.php';

$stats = db()->query("SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS avg_rating,
    SUM(status = 'Resolved') AS resolved FROM feedback")->fetch();
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>Your voice shapes better service.</h1>
    <p>FeedbackHub lets customers rate their experience, report problems and follow each response, while the support team tracks, replies to and resolves every message.</p>
    <div class="btn-row">
        <?php if (is_logged_in()): ?>
            <a class="btn btn-accent" href="<?= e(url(is_admin() ? 'admin/index.php' : 'feedback_new.php')) ?>"><?= is_admin() ? 'Open admin dashboard' : 'Give feedback' ?></a>
        <?php else: ?>
            <a class="btn btn-accent" href="<?= e(url('register.php')) ?>">Create a free account</a>
            <a class="btn btn-outline" href="<?= e(url('login.php')) ?>">Log in</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-3">
    <div class="stat"><p class="stat-label">Feedback received</p><p class="stat-value"><?= (int) $stats['total'] ?></p></div>
    <div class="stat"><p class="stat-label">Average rating</p><p class="stat-value"><?= e($stats['avg_rating'] ?? '0') ?> / 5</p></div>
    <div class="stat"><p class="stat-label">Issues resolved</p><p class="stat-value"><?= (int) $stats['resolved'] ?></p></div>
</section>

<h2 class="mt">How it works</h2>
<section class="grid grid-3">
    <div class="card"><div class="feature-icon">1</div><h3>Sign up securely</h3><p class="muted">Create an account. Your password is stored as a bcrypt hash, never as plain text.</p></div>
    <div class="card"><div class="feature-icon">2</div><h3>Share your experience</h3><p class="muted">Pick a category, give a 1-5 star rating and describe what happened.</p></div>
    <div class="card"><div class="feature-icon">3</div><h3>Track the response</h3><p class="muted">Follow the status from Pending to Resolved and read replies from the support team.</p></div>
</section>

<section class="card">
    <h2>About this project</h2>
    <p>This application was built for the topic <strong>“Building Web Applications: Web Application Security, Testing, Deployment and Review.”</strong> Each part of the topic has its own page:</p>
    <div class="grid grid-4">
        <a class="btn btn-outline" href="<?= e(url('security.php')) ?>">Security measures</a>
        <a class="btn btn-outline" href="<?= e(url('testing.php')) ?>">Testing results</a>
        <a class="btn btn-outline" href="<?= e(url('deployment.php')) ?>">Deployment guide</a>
        <a class="btn btn-outline" href="<?= e(url('review.php')) ?>">Project review</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
