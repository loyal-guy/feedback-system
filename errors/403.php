<?php
if (!defined('ROOT_PATH')) { require dirname(__DIR__) . '/includes/bootstrap.php'; http_response_code(403); }
$pageTitle = 'Access denied';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card error-page">
    <p class="error-code">403</p>
    <h1>Access denied</h1>
    <p class="muted">You do not have permission to view this page. This attempt has been logged.</p>
    <a class="btn" href="<?= e(url('index.php')) ?>">Go to home page</a>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
