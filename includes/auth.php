<?php
/**
 * includes/auth.php
 * Login with lockout (FR-1.4), roles (FR-1.2), inactivity logout (FR-1.7),
 * role checks on every protected page (NFR-3.2), bcrypt (NFR-3.1).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Try to log in. Returns ['ok' => bool, 'error' => ?string].
 * Error messages never reveal whether the username exists.
 */
function attempt_login(string $identifier, string $password): array
{
    $generic = 'Invalid username/email or password.';
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your username and password.'];
    }

    $stmt = db()->prepare(
        'SELECT user_id, full_name, username, role, password_hash, is_active, failed_attempts,
                (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked,
                GREATEST(0, CEIL(TIMESTAMPDIFF(SECOND, NOW(), locked_until) / 60)) AS minutes_left
           FROM users
          WHERE username = :id OR email = :id2
          LIMIT 1'
    );
    $stmt->execute([':id' => $identifier, ':id2' => $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        // Burn comparable time so response timing doesn't reveal valid usernames
        password_verify($password, '$2y$10$jH52Pe9ycdHeUKoOJLIH9.aGqZEdtJXjmJvwKDrPjYNg6d.2pprPe');
        audit_log('login_failed', 'users', null, 'Unknown identifier: ' . mb_substr($identifier, 0, 50), null);
        return ['ok' => false, 'error' => $generic];
    }

    $uid = (int)$user['user_id'];

    if ((int)$user['is_locked'] === 1) {
        $m = max(1, (int)$user['minutes_left']);
        audit_log('login_blocked_locked', 'users', $uid, null, $uid);
        return ['ok' => false, 'error' => "This account is temporarily locked. Try again in $m minute" . ($m === 1 ? '' : 's') . '.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        // Increment, and lock once the limit is reached
        $upd = db()->prepare(
            // NOTE: MySQL evaluates SET assignments left to right, so locked_until must be
            // computed BEFORE failed_attempts is incremented, or the lock fires one attempt early.
            'UPDATE users
                SET locked_until = CASE WHEN failed_attempts + 1 >= :max
                                        THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE)
                                        ELSE locked_until END,
                    failed_attempts = failed_attempts + 1
              WHERE user_id = :id'
        );
        $upd->execute([':max' => MAX_FAILED_ATTEMPTS, ':mins' => LOCKOUT_MINUTES, ':id' => $uid]);

        $chk = db()->prepare('SELECT failed_attempts, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked FROM users WHERE user_id = :id');
        $chk->execute([':id' => $uid]);
        $row = $chk->fetch();

        if ((int)$row['is_locked'] === 1) {
            // Reset the counter so the user gets a fresh set of attempts after the lock ends
            db()->prepare('UPDATE users SET failed_attempts = 0 WHERE user_id = :id')->execute([':id' => $uid]);
            audit_log('account_locked', 'users', $uid, 'Locked after ' . MAX_FAILED_ATTEMPTS . ' failed attempts', $uid);
            return ['ok' => false, 'error' => 'Too many failed attempts. This account is locked for ' . LOCKOUT_MINUTES . ' minutes.'];
        }

        audit_log('login_failed', 'users', $uid, 'Wrong password', $uid);
        $left = MAX_FAILED_ATTEMPTS - (int)$row['failed_attempts'];
        return ['ok' => false, 'error' => $generic . " ($left attempt" . ($left === 1 ? '' : 's') . ' left before lockout.)'];
    }

    if ((int)$user['is_active'] !== 1) {
        audit_log('login_blocked_inactive', 'users', $uid, null, $uid);
        return ['ok' => false, 'error' => 'This account is deactivated. Contact your administrator.'];
    }

    // Success
    db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = :id')
        ->execute([':id' => $uid]);

    // Upgrade the hash if PHP's default cost changed
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
            ->execute([':h' => password_hash($password, PASSWORD_BCRYPT), ':id' => $uid]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = $uid;
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
    // Fresh CSRF token after login, so a leaked pre-login token dies here
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    audit_log('login', 'users', $uid, null, $uid);
    return ['ok' => true, 'error' => null];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function current_user(): array
{
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'name'     => $_SESSION['full_name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ];
}

/**
 * Sign the user out: wipe all session data and issue a fresh session ID
 * (old one is deleted). A new empty session stays open so a flash message
 * such as "You have been signed out" can still be shown on the login page.
 */
function destroy_session(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Call at the top of every protected page.
 * - redirects to login if not signed in
 * - signs out after SESSION_TIMEOUT_MINUTES of inactivity
 * - signs out if the account was deactivated meanwhile
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $idleLimit = SESSION_TIMEOUT_MINUTES * 60;
    if (time() - ($_SESSION['last_activity'] ?? 0) > $idleLimit) {
        $uid = (int)$_SESSION['user_id'];
        audit_log('auto_logout', 'users', $uid, 'Inactivity timeout', $uid);
        destroy_session();
        flash('warning', 'You were signed out after ' . SESSION_TIMEOUT_MINUTES . ' minutes of inactivity.');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();

    // Re-check the account on each request so deactivation / role changes apply immediately
    $stmt = db()->prepare('SELECT is_active, role, full_name FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || (int)$u['is_active'] !== 1) {
        destroy_session();
        flash('danger', 'Your account is no longer active.');
        redirect('login.php');
    }
    $_SESSION['role']      = $u['role'];
    $_SESSION['full_name'] = $u['full_name'];
}

/** Admin-only pages (NFR-3.2): enforced here, not just hidden in the menu. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/forbidden.php';
        exit;
    }
}