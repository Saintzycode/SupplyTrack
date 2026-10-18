<?php
/**
 * includes/header.php
 * Shared layout (sidebar + top bar) matching the Figma design.
 * Set before including:  $pageTitle (string), $activePage (string key from the menu)
 */
require_once __DIR__ . '/auth.php';
require_login();

$pageTitle  = $pageTitle  ?? 'Dashboard';
$activePage = $activePage ?? '';
$user       = current_user();

// key => [label, file, bootstrap-icon, adminOnly]
$menu = [
    'dashboard'    => ['Dashboard',    'index.php',        'bi-grid',        false],
    'inventory'    => ['Inventory',    'inventory.php',    'bi-box-seam',    false],
    'categories'   => ['Categories',   'categories.php',   'bi-tags',        true],
    'suppliers'    => ['Suppliers',    'suppliers.php',    'bi-truck',       true],
    'schools'      => ['Schools',      'schools.php',      'bi-building',    true],
    'distribution' => ['Distribution', 'distribution.php', 'bi-send',        false],
    'reports'      => ['Reports',      'reports.php',      'bi-bar-chart',   true],
    'admin'        => ['Admin',        'admin.php',        'bi-shield-check', true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(ASSET_VER) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">

    <!-- Sidebar -->
    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <span class="brand-logo"><i class="bi bi-box-seam"></i></span>
            <span>
                <span class="brand-name"><?= e(APP_NAME) ?></span>
                <span class="brand-sub"><?= e(APP_SUBTITLE) ?></span>
            </span>
        </div>

        <div class="sidebar-label">MENU</div>
        <nav class="sidebar-nav">
            <?php foreach ($menu as $key => [$label, $file, $icon, $adminOnly]): ?>
                <?php if ($adminOnly && !is_admin()) continue; ?>
                <a href="<?= e(url($file)) ?>" class="nav-item<?= $activePage === $key ? ' active' : '' ?>">
                    <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-user">
            <span class="avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <span class="user-text">
                <span class="user-name"><?= e($user['name']) ?></span>
                <span class="user-role"><?= $user['role'] === 'admin' ? 'Administrator' : 'Inventory Clerk' ?></span>
            </span>
            <a href="<?= e(url('logout.php')) ?>" class="logout-link" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <!-- Main -->
    <div class="main">
        <header class="topbar">
            <button type="button" class="topbar-toggle" data-sidebar-toggle aria-label="Open menu" aria-controls="appSidebar">
                <i class="bi bi-list"></i>
            </button>
            <form class="topbar-search" action="<?= e(url('inventory.php')) ?>" method="get">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search items or SKU..." aria-label="Search items or SKU">
            </form>
            <div class="topbar-right">
                <span class="text-muted small today live-clock" data-timezone="<?= e(date_default_timezone_get()) ?>">
                    <?= e(date('D, M d, Y')) ?> &middot; <?= e(date('g:i:s A')) ?>
                </span>
                <span class="avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            </div>
        </header>

        <main class="content">
            <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>