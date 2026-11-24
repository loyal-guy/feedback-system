</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Customer Feedback System</p>
        <p class="muted">Project: Building Web Applications: Security, Testing, Deployment and Review &middot; Agu Prince</p>
        <p class="muted">
            <a href="<?= e(url('security.php')) ?>">Security</a> &middot;
            <a href="<?= e(url('testing.php')) ?>">Testing</a> &middot;
            <a href="<?= e(url('deployment.php')) ?>">Deployment</a> &middot;
            <a href="<?= e(url('review.php')) ?>">Review</a>
            <?php if (!IS_PRODUCTION): ?> &middot; <span class="badge badge-amber">Development mode</span><?php endif; ?>
        </p>
    </div>
</footer>
<?php foreach ($extraScripts ?? [] as $src): ?>
<script src="<?= e(url($src)) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
