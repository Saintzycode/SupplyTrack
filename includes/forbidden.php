<?php
/** Shown when a Staff user opens an admin-only URL directly (NFR-3.2). */
$activePage = '';
require __DIR__ . '/header.php';
?>
<div class="text-center py-5">
    <div class="display-1 text-danger"><i class="bi bi-shield-lock"></i></div>
    <h1 class="h3 mt-3">Access denied</h1>
    <p class="text-muted">You do not have permission to open this page.</p>
    <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">Back to dashboard</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>