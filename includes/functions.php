<?php
/**
 * includes/functions.php
 * Small helpers: escaping, redirects, flash messages, CSRF, formatting.
 */
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (FORCE_HTTPS && empty($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

/** HTML-escape. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Flash messages (shown once). Types: success, danger, warning, info. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** CSRF protection for every POST form. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Whole number 1..1000000 (no leading + or decimals). */
function valid_qty(mixed $v): bool
{
    return is_string($v) && $v !== '' && ctype_digit($v) && (int)$v >= 1 && (int)$v <= 1000000;
}

/** YYYY-MM-DD round-trip check. */
function valid_date(string $v): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    return $dt !== false && $dt->format('Y-m-d') === $v;
}

function money($amount): string
{
    return CURRENCY . number_format((float)$amount, 2);
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('M d, Y H:i', strtotime($dt)) : '';
}

function fmt_date(?string $dt): string
{
    return $dt ? date('M d, Y', strtotime($dt)) : '';
}