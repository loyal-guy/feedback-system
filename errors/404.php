<?php
if (!defined('ROOT_PATH')) { require dirname(__DIR__) . '/includes/bootstrap.php'; http_response_code(404); }
$pageTitle = 'Page not found';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card error-page">
    <p class="error-code">404</p>
    <h1>Page not found</h1>
    <p class="muted">The page or record you are looking for does not exist.</p>
    <a class="btn" href="<?= e(url('index.php')) ?>">Go to home page</a>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
