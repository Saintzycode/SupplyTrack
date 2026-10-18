<?php
/**
 * setup_admin.php - ONE-TIME page to create the first Administrator.
 * It refuses to run once any user exists. DELETE THIS FILE after use.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/audit.php';

$pdo = db();
$alreadySetup = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

$errors = [];
$done = false;
$old = ['full_name' => '', 'username' => '', 'email' => ''];

if (!$alreadySetup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $old['full_name'] = trim($_POST['full_name'] ?? '');
        $old['username']  = trim($_POST['username'] ?? '');
        $old['email']     = trim($_POST['email'] ?? '');
        $pw  = $_POST['password'] ?? '';
        $pw2 = $_POST['password2'] ?? '';

        if ($old['full_name'] === '')                                   $errors[] = 'Full name is required.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $old['username']))  $errors[] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))          $errors[] = 'Enter a valid email address.';
        if (strlen($pw) < 8)                                            $errors[] = 'Password must be at least 8 characters.';
        if ($pw !== $pw2)                                               $errors[] = 'Passwords do not match.';

        if (!$errors) {
            // Re-check inside POST: a second tab open from before setup completed
            // would otherwise crash on the unique key with a raw 500.
            $alreadySetup = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, username, email, password_hash, role)
                     VALUES (:n, :u, :e, :p, 'admin')"
                );
                $stmt->execute([
                    ':n' => $old['full_name'],
                    ':u' => $old['username'],
                    ':e' => $old['email'],
                    ':p' => password_hash($pw, PASSWORD_BCRYPT),
                ]);
                $newId = (int)$pdo->lastInsertId();
                audit_log('create', 'users', $newId, 'Initial administrator created via setup_admin.php', $newId);
                $done = true;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Setup already completed by another request. Reload this page.';
                } else {
                    error_log('setup_admin insert failed: ' . $e->getMessage());
                    $errors[] = 'Something went wrong while creating the account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Initial setup | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(ASSET_VER) ?>" rel="stylesheet">
</head>
<body>
<div class="login-form" style="min-height:100vh">
    <div class="login-card" style="max-width:480px">
        <h1 class="h4 fw-bold mb-1">Create the first administrator</h1>
        <p class="text-muted mb-4">One-time setup for <?= e(APP_NAME) ?>.</p>

        <?php if ($alreadySetup && !$done): ?>
            <div class="alert alert-warning">Setup is already complete (a user exists). For safety, <strong>delete <code>setup_admin.php</code></strong> from the server.</div>
            <a href="<?= e(url('login.php')) ?>" class="btn btn-primary">Go to sign in</a>
        <?php elseif ($done): ?>
            <div class="alert alert-success">Administrator account created.</div>
            <div class="alert alert-danger"><strong>Important:</strong> delete <code>setup_admin.php</code> now.</div>
            <a href="<?= e(url('login.php')) ?>" class="btn btn-primary">Go to sign in</a>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Full name</label>
                    <input class="form-control" name="full_name" value="<?= e($old['full_name']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= e($old['username']) ?>" autocomplete="off" required></div>
                <div class="mb-3"><label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= e($old['email']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="sa_password">Password (min. 8 characters)</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="sa_password" name="password" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="sa_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div></div>
                <div class="mb-4"><label class="form-label" for="sa_password2">Confirm password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="sa_password2" name="password2" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="sa_password2" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div></div>
                <button class="btn btn-primary w-100 py-2">Create administrator</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script src="<?= e(url('assets/js/password-toggle.js')) ?>?v=<?= e(ASSET_VER) ?>"></script>
</body>
</html>