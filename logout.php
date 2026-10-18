<?php
/** logout.php */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $uid = (int)$_SESSION['user_id'];
    audit_log('logout', 'users', $uid, null, $uid);
}
destroy_session();
flash('success', 'You have been signed out.');
redirect('login.php');