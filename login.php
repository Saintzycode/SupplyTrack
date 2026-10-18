<?php
/**
 * login.php - FR-1.1, FR-1.4 (lockout), FR-1.2 (role-based redirect)
 */
require_once __DIR__ . '/includes/auth.php';

/** Where each role lands after signing in. */
function landing_page(string $role): string
{
    return match ($role) {
        'admin' => 'index.php',
        default => 'index.php',   // staff: change to e.g. 'inventory.php' if you prefer
    };
}

if (is_logged_in()) {
    redirect(landing_page($_SESSION['role'] ?? 'staff'));
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    if (!csrf_verify()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $result = attempt_login($identifier, $_POST['password'] ?? '');
        if ($result['ok']) {
            redirect(landing_page($_SESSION['role']));
        }
        $error = $result['error'];
    }
}
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(ASSET_VER) ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="d-flex align-items-center gap-3">
            <span class="brand-logo" style="width:44px;height:44px;font-size:1.3rem"><i class="bi bi-box-seam"></i></span>
            <span>
                <span class="brand-name fs-5"><?= e(APP_NAME) ?></span>
                <span class="brand-sub">Inventory Management System</span>
            </span>
        </div>
        <div>
            <h2>Know exactly what is in stock, at all times.</h2>
            <div class="login-point"><span class="tick"><i class="bi bi-check"></i></span>Real-time stock visibility across all items</div>
            <div class="login-point"><span class="tick"><i class="bi bi-check"></i></span>Low-stock and reorder-point alerts</div>
            <div class="login-point"><span class="tick"><i class="bi bi-check"></i></span>Distribution records for every school</div>
        </div>
        <small>Single warehouse &nbsp;|&nbsp; Prices in PHP</small>
    </div>

    <div class="login-form">
        <div class="login-card">
            <h1 class="h3 fw-bold mb-1">Welcome back</h1>
            <p class="text-muted mb-4">Sign in to manage your inventory.</p>

            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="identifier" class="form-label">Username or email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="identifier" name="identifier"
                               value="<?= e($identifier) ?>" placeholder="Enter your username" autocomplete="username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="btn btn-outline-secondary" type="button" tabindex="-1"
                                data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
            </form>

            <div class="alert alert-warning small mt-4 mb-2 py-2">
                <i class="bi bi-lock me-1"></i>Account locks temporarily after <?= (int)MAX_FAILED_ATTEMPTS ?> failed attempts.
            </div>
            <div class="small text-muted">Forgot your password? Contact your administrator.</div>
        </div>
    </div>
</div>
<script src="<?= e(url('assets/js/password-toggle.js')) ?>?v=<?= e(ASSET_VER) ?>"></script>
</body>
</html>