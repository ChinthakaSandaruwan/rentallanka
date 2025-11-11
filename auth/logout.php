<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
    $f = __DIR__ . '/../error/error.log';
    if (is_readable($f)) {
        $lines = 100; $data = '';
        $fp = fopen($f, 'r');
        if ($fp) {
            fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
            while ($pos > 0 && $ln <= $lines) {
                $step = max(0, $pos - 4096); $read = $pos - $step;
                fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
                $ln = substr_count($chunk, "\n");
            }
            fclose($fp);
            $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
        }
        header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
    }
}

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
