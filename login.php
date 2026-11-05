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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(ASSET_VER) ?>" rel="stylesheet">
  <script data-theme-lock="1">
    (function(){ document.documentElement.dataset.theme = 'light'; })();
  </script>
</head>
<body>
<div class="login-wrap">
    <section class="login-form-panel">
        <div class="login-logo">
            <span class="login-mark"><i class="bi bi-box-seam"></i></span>
            <span>
                <span class="login-wordmark"><?= e(APP_NAME) ?></span>
                <span class="login-tagline">Inventory Management</span>
            </span>
        </div>

        <div class="login-center">
            <h1>Welcome back</h1>
            <p class="lead">Sign in to manage your inventory.</p>

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
                    <input type="text" class="form-control" id="identifier" name="identifier"
                           value="<?= e($identifier) ?>" placeholder="Enter your username" autocomplete="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrap">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="pw-toggle" type="button" tabindex="-1"
                                data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="login-help">Forgot password? Ask your admin.</div>
                <button type="submit" class="login-submit">Sign in</button>
            </form>

            <div class="login-note"><i class="bi bi-lock"></i><span>Account locks temporarily after <?= (int)MAX_FAILED_ATTEMPTS ?> failed attempts.</span></div>
        </div>

        <div class="login-foot">Single warehouse &nbsp;·&nbsp; Prices in PHP</div>
    </section>

    <aside class="login-hero" aria-hidden="true">
        <div class="hero-copy">
            <h2>Know exactly what is on the shelf.</h2>
            <p>Track stock, catch low items before they run out, and record every distribution to your schools.</p>

            <div class="stock-card">
                <div class="stock-head"><span>Stock status</span><span>On hand</span></div>
                <div class="stock-row">
                    <div><div class="stock-name">Long Coupon</div><div class="stock-cat">Paper Products</div></div>
                    <span class="badge-soft badge-low">Low</span><span class="stock-qty">0</span>
                </div>
                <div class="stock-row">
                    <div><div class="stock-name">Ballpen Black</div><div class="stock-cat">Writing Supplies</div></div>
                    <span class="badge-soft badge-normal">Normal</span><span class="stock-qty">50</span>
                </div>
                <div class="stock-row">
                    <div><div class="stock-name">Crayons 16 colors</div><div class="stock-cat">Art Supplies</div></div>
                    <span class="badge-soft badge-normal">Normal</span><span class="stock-qty">80</span>
                </div>
            </div>
        </div>
    </aside>
</div><script src="<?= e(url('assets/js/password-toggle.js')) ?>?v=<?= e(ASSET_VER) ?>"></script>
</body>
</html>
