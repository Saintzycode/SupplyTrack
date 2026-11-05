<?php
/**
 * admin.php - user management, own password change, low-stock alerts (admin only).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$me  = (int)current_user()['id'];

// ---- Handle POST *before* the page renders (Post/Redirect/Get) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Try again.');
        redirect('admin/admin.php');
    }
    if (isset($_POST['toggle_active_user'])) {
        $tid = (int)($_POST['target_id'] ?? 0);
        if ($tid === $me) {
            flash('danger', 'You cannot deactivate your own account.');
            redirect('admin/admin.php');
        }
        $row = $pdo->prepare('SELECT username, is_active, role FROM users WHERE user_id = :id');
        $row->execute([':id' => $tid]);
        $r = $row->fetch();
        if ($r) {
            $newState = (int)$r['is_active'] === 1 ? 0 : 1;
            if ($newState === 0 && $r['role'] === 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($admins <= 1) {
                    flash('danger', 'Cannot deactivate the last admin account.');
                    redirect('admin/admin.php');
                }
            }
            $pdo->prepare('UPDATE users SET is_active = :s, updated_at = NOW() WHERE user_id = :id')
                ->execute([':s' => $newState, ':id' => $tid]);
            audit_log($newState === 0 ? 'deactivate' : 'reactivate', 'users', $tid,
                ($newState === 0 ? 'Deactivated' : 'Reactivated') . " user: {$r['username']}", $me);
            flash('success', $newState === 0 ? 'Account deactivated.' : 'Account reactivated.');
        }
        redirect('admin/admin.php');
    }
    if (isset($_POST['update_user'])) {
        $uid = (int)($_POST['uid'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';
        $errs = [];
        if ($fullName === '' || mb_strlen($fullName) > 100) $errs[] = 'Full name is required (max 100).';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errs[] = 'Username must be 3-50 chars (letters, numbers, . _ -).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errs[] = 'Enter a valid email address.';
        $dc = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u AND user_id <> :id');
        $dc->execute([':u' => $username, ':id' => $uid]);
        if ((int)$dc->fetchColumn() > 0) $errs[] = 'Username already exists.';
        $de = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e AND user_id <> :id');
        $de->execute([':e' => $email, ':id' => $uid]);
        if ((int)$de->fetchColumn() > 0) $errs[] = 'Email already exists.';
        if ($uid === $me) $role = 'admin';
        if ($role === 'staff') {
            $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            $meRow = $pdo->prepare('SELECT role FROM users WHERE user_id = :id');
            $meRow->execute([':id' => $uid]);
            if ($admins <= 1 && $meRow->fetchColumn() === 'admin') {
                $errs[] = 'Cannot demote the last admin account.';
            }
        }
        if ($errs) {
            flash('danger', implode(' ', $errs));
            redirect('admin/admin.php?mode=edit&uid=' . $uid);
        }
        $pdo->prepare('UPDATE users SET full_name = :n, username = :u, email = :e, role = :r, updated_at = NOW() WHERE user_id = :id')
            ->execute([':n' => $fullName, ':u' => $username, ':e' => $email, ':r' => $role, ':id' => $uid]);
        audit_log('update', 'users', $uid, "Updated account: {$username} (role {$role})", $me);
        flash('success', 'Account updated.');
        redirect('admin/admin.php');
    }
    if (isset($_POST['create_user'])) {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password2'] ?? '');
        $errs = [];
        if ($fullName === '' || mb_strlen($fullName) > 100) $errs[] = 'Full name is required (max 100).';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errs[] = 'Username must be 3-50 chars (letters, numbers, . _ -).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errs[] = 'Enter a valid email address.';
        if (strlen($pw) < 8) $errs[] = 'Password must be at least 8 characters.';
        if ($pw !== $pw2) $errs[] = 'Passwords do not match.';
        $dc = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $dc->execute([':u' => $username]);
        if ((int)$dc->fetchColumn() > 0) $errs[] = 'Username already exists.';
        $de = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
        $de->execute([':e' => $email]);
        if ((int)$de->fetchColumn() > 0) $errs[] = 'Email already exists.';
        if ($errs) {
            flash('danger', implode(' ', $errs));
            redirect('admin/admin.php?mode=add');
        }
        try {
            $ins = $pdo->prepare(
                "INSERT INTO users (full_name, username, email, password_hash, role, is_active)
                 VALUES (:n, :u, :e, :p, :r, 1)"
            );
            $ins->execute([':n' => $fullName, ':u' => $username, ':e' => $email, ':p' => password_hash($pw, PASSWORD_BCRYPT), ':r' => $role]);
            $newId = (int)$pdo->lastInsertId();
            audit_log('create', 'users', $newId, "Created {$role} account: {$username}", $me);
            flash('success', 'Account created.');
            redirect('admin/admin.php');
        } catch (PDOException $e) {
            flash('danger', 'Something went wrong. Please try again.');
            if ($e->getCode() !== '23000') error_log('admin create user failed: ' . $e->getMessage());
            redirect('admin/admin.php?mode=add');
        }
    }
    if (isset($_POST['reset_pw'])) {
        $tid = (int)($_POST['uid'] ?? 0);
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = $pdo->prepare('SELECT username, role FROM users WHERE user_id = :id');
        $row->execute([':id' => $tid]);
        $target = $row->fetch();
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('admin/admin.php');
        }
        if ($target['role'] !== 'staff') {
            flash('danger', 'Passwords can only be reset for Staff accounts.');
            redirect('admin/admin.php');
        }
        if (strlen($new) < 8) {
            flash('danger', 'New password must be at least 8 characters.');
            redirect('admin/admin.php?mode=reset&uid=' . $tid);
        }
        if ($new !== $confirm) {
            flash('danger', 'New passwords do not match.');
            redirect('admin/admin.php?mode=reset&uid=' . $tid);
        }
        $pdo->prepare('UPDATE users SET password_hash = :h, failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE user_id = :id')
            ->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $tid]);
        audit_log('password_reset', 'users', $tid, "Administrator reset password for: {$target['username']}", $me);
        flash('success', "Password reset for {$target['username']}.");
        redirect('admin/admin.php');
    }
    if (isset($_POST['change_own_pw'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
        $row->execute([':id' => $me]);
        $hash = $row->fetchColumn();
        if (!password_verify($current, (string)$hash)) {
            flash('danger', 'Current password is incorrect.');
            redirect('admin/admin.php');
        }
        if (strlen($new) < 8) {
            flash('danger', 'New password must be at least 8 characters.');
            redirect('admin/admin.php');
        }
        if ($new !== $confirm) {
            flash('danger', 'New passwords do not match.');
            redirect('admin/admin.php');
        }
        $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = NOW() WHERE user_id = :id')
            ->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $me]);
        audit_log('password_change', 'users', $me, 'Administrator changed own password', $me);
        flash('success', 'Your password has been updated.');
        redirect('admin/admin.php');
    }
    redirect('admin/admin.php');
}

$pageTitle  = 'Admin';
$activePage = 'admin';

$addOld = ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'staff'];
$editUser = null;
$mode = $_GET['mode'] ?? 'list';
if (!in_array($mode, ['list', 'add', 'edit', 'reset'], true)) {
    $mode = 'list';
}
$uid  = (int)($_GET['uid'] ?? 0);
if ($mode === 'edit' && $uid > 0) {
    $st = $pdo->prepare('SELECT user_id, full_name, username, email, role, is_active, last_login, failed_attempts, locked_until FROM users WHERE user_id = :id');
    $st->execute([':id' => $uid]);
    $editUser = $st->fetch();
    if (!$editUser) {
        flash('danger', 'User not found.');
        redirect('admin/admin.php');
    }
}
$editOld = $editUser ?: ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'staff', 'is_active' => 1];
$errors = [];
$alerts = $pdo->query(
    'SELECT a.alert_id, a.item_id, a.quantity_at_alert, a.reorder_point, a.created_at,
            i.sku, i.item_name, i.quantity_on_hand
       FROM low_stock_alerts a
       JOIN items i ON i.item_id = a.item_id
      WHERE a.is_resolved = 0
      ORDER BY a.created_at DESC'
)->fetchAll();
$users = $pdo->query(
    'SELECT user_id, full_name, username, email, role, is_active, last_login, failed_attempts, locked_until
       FROM users
       ORDER BY role DESC, full_name ASC'
)->fetchAll();

// ---- Handle own password change ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_own_pw'])) {
    if (!csrf_verify()) {
        $errors['ownpw'] = 'Session expired. Please try again.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $row = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
        $row->execute([':id' => $me]);
        $hash = $row->fetchColumn();

        if ($current === '' || $new === '' || $confirm === '') {
            $errors['ownpw'] = 'All password fields are required.';
        } elseif (!password_verify($current, (string)$hash)) {
            $errors['ownpw'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors['ownpw'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors['ownpw'] = 'New passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = NOW() WHERE user_id = :id')
                ->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $me]);
            audit_log('password_change', 'users', $me, 'Administrator changed own password', $me);
            flash('success', 'Your password has been updated.');
            redirect('admin/admin.php');
        }
    }
}

// ---- Toggle active / (soft-)delete user ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active_user'])) {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Try again.');
        redirect('admin/admin.php');
    }
    $tid = (int)($_POST['target_id'] ?? 0);
    if ($tid === $me) {
        flash('danger', 'You cannot deactivate your own account.');
        redirect('admin/admin.php');
    }
    $row = $pdo->prepare('SELECT username, is_active, role FROM users WHERE user_id = :id');
    $row->execute([':id' => $tid]);
    $r = $row->fetch();
    if ($r) {
        $newState = (int)$r['is_active'] === 1 ? 0 : 1;
        if ($newState === 0 && $r['role'] === 'admin') {
            $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($admins <= 1) {
                flash('danger', 'Cannot deactivate the last admin account.');
                redirect('admin/admin.php');
            }
        }
        $pdo->prepare('UPDATE users SET is_active = :s, updated_at = NOW() WHERE user_id = :id')
            ->execute([':s' => $newState, ':id' => $tid]);
        audit_log($newState === 0 ? 'deactivate' : 'reactivate', 'users', $tid,
            ($newState === 0 ? 'Deactivated' : 'Reactivated') . " user: {$r['username']}", $me);
        flash('success', $newState === 0 ? 'Account deactivated.' : 'Account reactivated.');
    }
    redirect('admin/admin.php');
}

// ---- Create staff ----
$addOld = ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'staff'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (!csrf_verify()) {
        $errors['create'] = 'Session expired. Please try again.';
    } else {
        $addOld['full_name'] = trim($_POST['full_name'] ?? '');
        $addOld['username']  = trim($_POST['username'] ?? '');
        $addOld['email']     = trim($_POST['email'] ?? '');
        $addOld['role']      = in_array($_POST['role'] ?? '', ['admin','staff'], true) ? $_POST['role'] : 'staff';
        $pw  = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password2'] ?? '');

        if ($addOld['full_name'] === '') $errors['create_full_name'] = 'Full name is required.';
        elseif (mb_strlen($addOld['full_name']) > 100) $errors['create_full_name'] = 'Full name must be 100 characters or fewer.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $addOld['username'])) $errors['create_username'] = 'Username must be 3-50 chars (letters, numbers, . _ -).';
        if (!filter_var($addOld['email'], FILTER_VALIDATE_EMAIL)) $errors['create_email'] = 'Enter a valid email address.';
        elseif (mb_strlen($addOld['email']) > 100) $errors['create_email'] = 'Email must be 100 characters or fewer.';
        if (strlen($pw) < 8) $errors['create_password'] = 'Password must be at least 8 characters.';
        if ($pw !== $pw2) $errors['create_password2'] = 'Passwords do not match.';

        // duplicate checks
        if (!isset($errors['create_username'])) {
            $dc = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
            $dc->execute([':u' => $addOld['username']]);
            if ((int)$dc->fetchColumn() > 0) $errors['create_username'] = 'Username already exists.';
        }
        if (!isset($errors['create_email'])) {
            $de = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
            $de->execute([':e' => $addOld['email']]);
            if ((int)$de->fetchColumn() > 0) $errors['create_email'] = 'Email already exists.';
        }

        if (!array_filter($errors, fn($k) => str_starts_with($k, 'create_'), ARRAY_FILTER_USE_KEY)) {
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO users (full_name, username, email, password_hash, role, is_active)
                     VALUES (:n, :u, :e, :p, :r, 1)"
                );
                $ins->execute([
                    ':n' => $addOld['full_name'],
                    ':u' => $addOld['username'],
                    ':e' => $addOld['email'],
                    ':p' => password_hash($pw, PASSWORD_BCRYPT),
                    ':r' => $addOld['role'],
                ]);
                $newId = (int)$pdo->lastInsertId();
                audit_log('create', 'users', $newId, "Created {$addOld['role']} account: {$addOld['username']}", $me);
                flash('success', 'Account created.');
                redirect('admin/admin.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    // figure out which field
                    if (stripos($e->getMessage(), 'username') !== false) {
                        $errors['create_username'] = 'Username already exists.';
                    } elseif (stripos($e->getMessage(), 'email') !== false) {
                        $errors['create_email'] = 'Email already exists.';
                    } else {
                        $errors['create'] = 'Duplicate entry.';
                    }
                } else {
                    error_log('admin create user failed: ' . $e->getMessage());
                    $errors['create'] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// ---- Edit user ----
$editUser = null;
if ($mode === 'edit' && $uid > 0) {
    $st = $pdo->prepare('SELECT user_id, full_name, username, email, role, is_active, last_login, failed_attempts, locked_until FROM users WHERE user_id = :id');
    $st->execute([':id' => $uid]);
    $editUser = $st->fetch();
    if (!$editUser) {
        flash('danger', 'User not found.');
        redirect('admin/admin.php');
    }
}

$editOld = $editUser ?: ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'staff', 'is_active' => 1];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    if (!csrf_verify()) {
        $errors['edit'] = 'Session expired. Please try again.';
    } else {
        $uid = (int)($_POST['uid'] ?? 0);
        $editOld['full_name'] = trim($_POST['full_name'] ?? '');
        $editOld['username']  = trim($_POST['username'] ?? '');
        $editOld['email']     = trim($_POST['email'] ?? '');
        $editOld['role']      = in_array($_POST['role'] ?? '', ['admin','staff'], true) ? $_POST['role'] : 'staff';
        // cannot demote self
        if ($uid === $me) {
            $editOld['role'] = 'admin';
        }

        if ($editOld['full_name'] === '') $errors['edit_full_name'] = 'Full name is required.';
        elseif (mb_strlen($editOld['full_name']) > 100) $errors['edit_full_name'] = 'Full name must be 100 characters or fewer.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $editOld['username'])) $errors['edit_username'] = 'Username must be 3-50 chars (letters, numbers, . _ -).';
        if (!filter_var($editOld['email'], FILTER_VALIDATE_EMAIL)) $errors['edit_email'] = 'Enter a valid email address.';
        elseif (mb_strlen($editOld['email']) > 100) $errors['edit_email'] = 'Email must be 100 characters or fewer.';

        // duplicate checks
        if (!isset($errors['edit_username'])) {
            $dc = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u AND user_id <> :id');
            $dc->execute([':u' => $editOld['username'], ':id' => $uid]);
            if ((int)$dc->fetchColumn() > 0) $errors['edit_username'] = 'Username already exists.';
        }
        if (!isset($errors['edit_email'])) {
            $de = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e AND user_id <> :id');
            $de->execute([':e' => $editOld['email'], ':id' => $uid]);
            if ((int)$de->fetchColumn() > 0) $errors['edit_email'] = 'Email already exists.';
        }

        if (!isset($errors['edit_role']) && $editOld['role'] === 'staff') {
            $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($admins <= 1) {
                $meRow = $pdo->prepare('SELECT role FROM users WHERE user_id = :id');
                $meRow->execute([':id' => $uid]);
                if ($meRow->fetchColumn() === 'admin') {
                    $errors['edit_role'] = 'Cannot demote the last admin account.';
                }
            }
        }

        if (!array_filter($errors, fn($k) => str_starts_with($k, 'edit_'), ARRAY_FILTER_USE_KEY)) {
            try {
                $upd = $pdo->prepare(
                    'UPDATE users SET full_name = :n, username = :u, email = :e, role = :r, updated_at = NOW()
                      WHERE user_id = :id'
                );
                $upd->execute([
                    ':n' => $editOld['full_name'],
                    ':u' => $editOld['username'],
                    ':e' => $editOld['email'],
                    ':r' => $editOld['role'],
                    ':id' => $uid,
                ]);
                audit_log('update', 'users', $uid, "Updated account: {$editOld['username']} (role {$editOld['role']})", $me);
                flash('success', 'Account updated.');
                redirect('admin/admin.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    if (stripos($e->getMessage(), 'username') !== false) $errors['edit_username'] = 'Username already exists.';
                    elseif (stripos($e->getMessage(), 'email') !== false) $errors['edit_email'] = 'Email already exists.';
                    else $errors['edit'] = 'Duplicate entry.';
                } else {
                    error_log('admin edit user failed: ' . $e->getMessage());
                    $errors['edit'] = 'Something went wrong. Please try again.';
                }
            }
        }
        if ($errors) {
            $mode = 'edit';
        }
    }
}

// ---- Reset password for a staff account ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pw'])) {
    if (!csrf_verify()) {
        $errors['reset'] = 'Session expired. Please try again.';
    } else {
        $tid = (int)($_POST['uid'] ?? 0);
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = $pdo->prepare('SELECT username, role FROM users WHERE user_id = :id');
        $row->execute([':id' => $tid]);
        $target = $row->fetch();
        if (!$target) {
            $errors['reset'] = 'User not found.';
        } elseif ($target['role'] !== 'staff') {
            $errors['reset'] = 'Passwords can only be reset for Staff accounts.';
        } elseif (strlen($new) < 8) {
            $errors['reset'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors['reset'] = 'New passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = :h, failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE user_id = :id')
                ->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $tid]);
            audit_log('password_reset', 'users', $tid, "Administrator reset password for: {$target['username']}", $me);
            flash('success', "Password reset for {$target['username']}.");
            redirect('admin/admin.php');
        }
        if ($errors) {
            $mode = 'reset';
            $uid = $tid;
        }
    }
}

// ---- Low-stock alerts ----
$alerts = $pdo->query(
    'SELECT a.alert_id, a.item_id, a.quantity_at_alert, a.reorder_point, a.created_at,
            i.sku, i.item_name, i.quantity_on_hand
       FROM low_stock_alerts a
       JOIN items i ON i.item_id = a.item_id
      WHERE a.is_resolved = 0
      ORDER BY a.created_at DESC'
)->fetchAll();

// ---- User list (for list mode) ----
$users = $pdo->query(
    'SELECT user_id, full_name, username, email, role, is_active, last_login, failed_attempts, locked_until
       FROM users
       ORDER BY role DESC, full_name ASC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Administration</h1>
        <p>User accounts, password resets, and open low-stock alerts.</p>
    </div>
    <?php if ($mode === 'list'): ?>
        <a href="<?= e(url('admin/admin.php?mode=add')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add staff account</a>
    <?php else: ?>
        <a href="<?= e(url('admin/admin.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
    <?php endif; ?>
</div>

<?php if ($mode === 'add'): ?>
    <!-- Add staff -->
    <div class="card-ui p-4 mb-4">
        <h2 class="h6 fw-bold mb-3">Add staff account</h2>
        <?php if (!empty($errors['create'])): ?>
            <div class="alert alert-danger"><?= e($errors['create']) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="create_user" value="1">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_full_name">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['create_full_name']) ? 'is-invalid' : '' ?>" id="ca_full_name" name="full_name" value="<?= e($addOld['full_name']) ?>" maxlength="100" required>
                    <?php if (isset($errors['create_full_name'])): ?><div class="invalid-feedback"><?= e($errors['create_full_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_username">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['create_username']) ? 'is-invalid' : '' ?>" id="ca_username" name="username" value="<?= e($addOld['username']) ?>" maxlength="50" required>
                    <?php if (isset($errors['create_username'])): ?><div class="invalid-feedback"><?= e($errors['create_username']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?= isset($errors['create_email']) ? 'is-invalid' : '' ?>" id="ca_email" name="email" value="<?= e($addOld['email']) ?>" maxlength="100" required>
                    <?php if (isset($errors['create_email'])): ?><div class="invalid-feedback"><?= e($errors['create_email']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_role">Role</label>
                    <select class="form-select" id="ca_role" name="role">
                        <option value="staff" <?= $addOld['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= $addOld['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_password">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control <?= isset($errors['create_password']) ? 'is-invalid' : '' ?>" id="ca_password" name="password" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="ca_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        <?php if (isset($errors['create_password'])): ?><div class="invalid-feedback"><?= e($errors['create_password']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ca_password2">Confirm password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control <?= isset($errors['create_password2']) ? 'is-invalid' : '' ?>" id="ca_password2" name="password2" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="ca_password2" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        <?php if (isset($errors['create_password2'])): ?><div class="invalid-feedback"><?= e($errors['create_password2']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Create account</button>
                <a href="<?= e(url('admin/admin.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($mode === 'edit' && $editUser): ?>
    <!-- Edit user -->
    <div class="card-ui p-4 mb-4">
        <h2 class="h6 fw-bold mb-3">Edit account: <?= e($editUser['username']) ?></h2>
        <?php if (!empty($errors['edit'])): ?>
            <div class="alert alert-danger"><?= e($errors['edit']) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="uid" value="<?= (int)$editUser['user_id'] ?>">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ed_full_name">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['edit_full_name']) ? 'is-invalid' : '' ?>" id="ed_full_name" name="full_name" value="<?= e($editOld['full_name']) ?>" maxlength="100" required>
                    <?php if (isset($errors['edit_full_name'])): ?><div class="invalid-feedback"><?= e($errors['edit_full_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ed_username">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['edit_username']) ? 'is-invalid' : '' ?>" id="ed_username" name="username" value="<?= e($editOld['username']) ?>" maxlength="50" required>
                    <?php if (isset($errors['edit_username'])): ?><div class="invalid-feedback"><?= e($errors['edit_username']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ed_email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?= isset($errors['edit_email']) ? 'is-invalid' : '' ?>" id="ed_email" name="email" value="<?= e($editOld['email']) ?>" maxlength="100" required>
                    <?php if (isset($errors['edit_email'])): ?><div class="invalid-feedback"><?= e($errors['edit_email']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="ed_role">Role</label>
                    <select class="form-select" id="ed_role" name="role" <?= (int)$editUser['user_id'] === $me ? 'disabled' : '' ?>>
                        <option value="staff" <?= $editOld['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= $editOld['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <?php if ((int)$editUser['user_id'] === $me): ?>
                        <input type="hidden" name="role" value="admin">
                        <div class="form-text">You cannot change your own role.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="<?= e(url('admin/admin.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($mode === 'reset' && $uid > 0): ?>
    <?php
        $tr = $pdo->prepare('SELECT user_id, full_name, username, role FROM users WHERE user_id = :id');
        $tr->execute([':id' => $uid]);
        $target = $tr->fetch();
    ?>
    <?php if (!$target || $target['role'] !== 'staff'): ?>
        <div class="alert alert-danger">Password can only be reset for Staff accounts.</div>
        <a href="<?= e(url('admin/admin.php')) ?>" class="btn btn-outline-secondary">Back</a>
    <?php else: ?>
        <div class="card-ui p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Reset password: <?= e($target['full_name']) ?> <span class="text-muted fw-normal">(<?= e($target['username']) ?>)</span></h2>
            <?php if (!empty($errors['reset'])): ?>
                <div class="alert alert-danger"><?= e($errors['reset']) ?></div>
            <?php endif; ?>
            <p class="text-muted small">This clears any lockout on the account. No email is sent.</p>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="reset_pw" value="1">
                <input type="hidden" name="uid" value="<?= (int)$target['user_id'] ?>">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="rp_new">New password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="rp_new" name="new_password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="rp_new" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="rp_confirm">Confirm password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="rp_confirm" name="confirm_password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="rp_confirm" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                    <button type="submit" class="btn btn-primary">Reset password</button>
                    <button type="button" class="btn btn-outline-primary" id="genPwBtn"
                            data-target="rp_new" data-target2="rp_confirm">
                        <i class="bi bi-shuffle me-1"></i>Generate
                    </button>
                    <a href="<?= e(url('admin/admin.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    <span class="text-muted small ms-auto">Generate fills a random temporary password you can read and hand over.</span>
                </div>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($mode === 'list'): ?>
    <!-- User list -->
    <div class="card-ui mb-4">
        <div class="table-responsive">
            <table class="table table-ui">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                            $locked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time();
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($u['full_name']) ?></td>
                            <td class="text-muted"><?= e($u['username']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge-soft badge-adj">Admin</span>
                                <?php else: ?>
                                    <span class="badge-soft badge-in">Staff</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$u['is_active'] !== 1): ?>
                                    <span class="badge-soft badge-inactive">Inactive</span>
                                <?php elseif ($locked): ?>
                                    <span class="badge-soft badge-low">Locked</span>
                                <?php else: ?>
                                    <span class="badge-soft badge-normal">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= e($u['last_login'] ? fmt_datetime($u['last_login']) : 'Never') ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('admin/admin.php?mode=edit&uid=' . (int)$u['user_id'])) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['role'] === 'staff'): ?>
                                    <a href="<?= e(url('admin/admin.php?mode=reset&uid=' . (int)$u['user_id'])) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-key"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ((int)$u['user_id'] !== $me): ?>
                                    <form method="post" class="d-inline js-toggle-user" data-deactivating="<?= (int)$u['is_active'] === 1 ? '1' : '0' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="toggle_active_user" value="1">
                                        <input type="hidden" name="target_id" value="<?= (int)$u['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= (int)$u['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="Toggle active">
                                            <i class="bi <?= (int)$u['is_active'] === 1 ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Own password change + low-stock alerts side by side -->
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Change your password</h2>
                <?php if (!empty($errors['ownpw'])): ?>
                    <div class="alert alert-danger"><?= e($errors['ownpw']) ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="change_own_pw" value="1">
                    <div class="mb-3">
                        <label class="form-label" for="pw_current">Current password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="pw_current" name="current_password" autocomplete="current-password" required>
                            <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="pw_current" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pw_new">New password (min. 8) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="pw_new" name="new_password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="pw_new" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pw_confirm">Confirm new password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="pw_confirm" name="confirm_password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" tabindex="-1" data-toggle-password="pw_confirm" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card-ui p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Open low-stock alerts</h2>
                    <span class="badge-soft badge-low"><?= count($alerts) ?></span>
                </div>
                <?php if (!$alerts): ?>
                    <p class="text-muted small mb-0">No open low-stock alerts.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-ui mb-0">
                            <thead>
                                <tr><th>Item</th><th class="text-end">At alert</th><th class="text-end">Reorder</th><th>Since</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $a): ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <?= e($a['item_name']) ?>
                                            <span class="text-muted small d-block"><?= e($a['sku']) ?></span>
                                        </td>
                                        <td class="text-end text-danger fw-semibold"><?= number_format((int)$a['quantity_at_alert']) ?></td>
                                        <td class="text-end text-muted"><?= number_format((int)$a['reorder_point']) ?></td>
                                        <td class="text-muted small"><?= e(fmt_date($a['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Alerts resolve automatically once stock returns above the reorder point.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    var btn = document.getElementById('genPwBtn');
    if (!btn) return;
    var rand = function (n) {
        var a = new Uint32Array(1);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(a);
            return a[0] % n;
        }
        return Math.floor(Math.random() * n);
    };
    btn.addEventListener('click', function () {
        var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        var lower = 'abcdefghijkmnpqrstuvwxyz';
        var digits = '23456789';
        var all = upper + lower + digits;
        var pw = upper[rand(upper.length)] + lower[rand(lower.length)] + digits[rand(digits.length)];
        while (pw.length < 10) { pw += all[rand(all.length)]; }
        pw = pw.split('').sort(function () { return rand(2) - 1; }).join('');

        [btn.getAttribute('data-target'), btn.getAttribute('data-target2')].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = pw; el.type = 'text'; }
            var toggle = document.querySelector('[data-toggle-password="' + id + '"]');
            if (toggle) {
                var ic = toggle.querySelector('i');
                if (ic) { ic.classList.remove('bi-eye'); ic.classList.add('bi-eye-slash'); }
            }
        });
    });
})();
</script>

<!-- Deactivate / reactivate user confirmation (progressive enhancement: without JS the row form submits directly) -->
<div class="modal fade" id="toggleUserModal" tabindex="-1" aria-labelledby="toggleUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleUserModalLabel">Deactivate user</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="toggleUserMessage" class="mb-0">Do you want to deactivate this user?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="toggleUserConfirm">Deactivate</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modalEl = document.getElementById('toggleUserModal');
    if (!modalEl) return;
    var titleEl   = document.getElementById('toggleUserModalLabel');
    var msgEl     = document.getElementById('toggleUserMessage');
    var confirmEl = document.getElementById('toggleUserConfirm');
    var pending   = null;   // the row form waiting for confirmation

    document.querySelectorAll('.js-toggle-user').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.bootstrap) return;   // no Bootstrap JS: the form submits normally
            ev.preventDefault();
            pending = form;
            var off = form.getAttribute('data-deactivating') === '1';
            titleEl.textContent   = off ? 'Deactivate user' : 'Reactivate user';
            msgEl.textContent     = off ? 'Do you want to deactivate this user?' : 'Do you want to reactivate this user?';
            confirmEl.textContent = off ? 'Deactivate' : 'Reactivate';
            confirmEl.className   = off ? 'btn btn-danger' : 'btn btn-success';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    confirmEl.addEventListener('click', function () {
        if (pending) pending.submit();   // submit() does not fire the submit event, so no loop
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
