<?php
require_once __DIR__ . '/../config/config.php';

// Clear all session data and destroy session
$_SESSION['super_admin_id'] = null;
unset(
    $_SESSION['super_admin_id'],
    $_SESSION['user'],
    $_SESSION['loggedin'],
    $_SESSION['role'],
    $_SESSION['otp_stage'],
    $_SESSION['otp_user_id'],
    $_SESSION['otp_phone'],
    $_SESSION['sa_stage'],
    $_SESSION['sa_pending']
);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect with flash so SweetAlert2 shows a small alert on landing page
redirect_with_message(rtrim($base_url, '/') . '/index.php', 'Logged out', 'info');
exit;
?>
