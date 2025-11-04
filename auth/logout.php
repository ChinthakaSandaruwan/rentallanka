<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
header('Location: ../index.php');
exit;
?>
