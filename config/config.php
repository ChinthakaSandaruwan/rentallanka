<?php
require_once __DIR__ . '/security_bootstrap.php';
// Base URL

$base_url = 'http://localhost/rentallanka';

// Centralize error logging to project log file
try {
    $logFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    if (function_exists('ini_set')) {
        @ini_set('log_errors', '1');
        @ini_set('error_log', $logFile);
        // Optional: keep output clean in production; in dev you may set to '1'
        @ini_set('display_errors', '0');
    }
} catch (Throwable $e) {
    // swallow any logging setup issues
}

if (!function_exists('app_log')) {
    function app_log(string $msg): void { error_log($msg); }
}

// Database connection for XAMPP default setup
// Adjust credentials as needed for your environment
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '123321555');
define('DB_NAME', 'rentallanka');

$smslenz_user_id = getenv('SMSLENZ_USER_ID') ?: '';
$smslenz_api_key = getenv('SMSLENZ_API_KEY') ?: '';
$smslenz_sender_id = getenv('SMSLENZ_SENDER_ID') ?: 'SMSlenzDEMO';
$smslenz_base = 'https://smslenz.lk/api';

// Optional: Redis-backed sessions (set SESSION_REDIS like "tcp://127.0.0.1:6379?database=1")
try {
    $sessRedis = getenv('SESSION_REDIS') ?: '';
    if ($sessRedis !== '' && function_exists('ini_set')) {
        @ini_set('session.save_handler', 'redis');
        @ini_set('session.save_path', $sessRedis);
        if (!ini_get('session.gc_maxlifetime')) { @ini_set('session.gc_maxlifetime', '86400'); }

    }
} catch (Throwable $e) { /* ignore */ }

$__session_status = function_exists('session_status') ? session_status() : PHP_SESSION_NONE;
if ($__session_status === PHP_SESSION_NONE) {
    session_start();
}

// Fallback alert function used by global handlers. If an app-level
// implementation exists elsewhere, that will override this.
if (!function_exists('send_system_alert')) {
    function send_system_alert(string $title, string $message): void {
        error_log('[system_alert] ' . $title . ': ' . $message);
    }
}

/**
 * Install global handlers to automatically raise system alerts
 * for unhandled exceptions, severe PHP errors, and fatal shutdowns.
 */
function install_system_alert_handlers(): void {
    static $installed = false;
    if ($installed) return; $installed = true;

    set_exception_handler(function (Throwable $ex) {
        error_log('[Unhandled Exception] ' . $ex->getMessage());
        // Keep message concise to avoid leaking sensitive data
        send_system_alert('Unhandled Exception', $ex->getMessage());
    });

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
        // Only alert on severe, user-actionable errors
        $severe = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($errno, $severe, true)) {
            $msg = $errstr . ' in ' . $errfile . ':' . $errline;
            send_system_alert('PHP Error', $msg);
        }
        // Return false to allow normal PHP handling as well
        return false;
    });

    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $msg = $e['message'] . ' in ' . $e['file'] . ':' . $e['line'];
            send_system_alert('Fatal Error', $msg);
        }
    });
}

// Ensure handlers are active for all requests including CLI scripts that include config.php
install_system_alert_handlers();

$__use_persistent = (getenv('DB_PERSISTENT') === '1');
$__db_host = $__use_persistent ? ('p:' . DB_HOST) : DB_HOST;
$mysqli = new mysqli($__db_host, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}

$mysqli->set_charset('utf8mb4');

function db(): mysqli {
    global $mysqli;
    return $mysqli;
}

function redirect_with_message(string $url, string $msg, string $type = 'success'): void {
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    header('Location: ' . $url . $sep . 'flash=' . urlencode($msg) . '&type=' . urlencode($type));
    exit;
}

function get_flash(): array {
    $msg = $_GET['flash'] ?? '';
    $type = $_GET['type'] ?? 'success';
    return [$msg, $type];
}

function setting_get(string $key, ?string $default = null): ?string {
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row && $row['setting_value'] !== null) {
        return (string)$row['setting_value'];
    }
    return $default;
}

function setting_set(string $key, ?string $value): bool {
    $sql = 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) AS new ON DUPLICATE KEY UPDATE setting_value = new.setting_value';
    $stmt = db()->prepare($sql);
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// OTP Mode: 'development' or 'real' (dynamic via settings)
// Define after DB and helpers are available
if (!defined('OTP_MODE')) {
    try {
        $mode = strtolower((string)(setting_get('otp_mode', 'development')));
        if ($mode !== 'real') { $mode = 'development'; }
        define('OTP_MODE', $mode);
    } catch (Throwable $e) {
        define('OTP_MODE', 'development');
    }
}

function smslenz_send_sms(string $to, string $message): array {
    global $smslenz_user_id, $smslenz_api_key, $smslenz_sender_id, $smslenz_base;
    $conf_user = setting_get('sms_user_id', $smslenz_user_id);
    $conf_key = setting_get('sms_api_key', $smslenz_api_key);
    $conf_sender = setting_get('sms_sender_id', $smslenz_sender_id);
    $conf_base = setting_get('sms_base_url', $smslenz_base);
    $to = trim($to);
    if (!preg_match('/^\+94\d{9}$/', $to)) {
        return ['ok' => false, 'error' => 'Invalid phone format'];
    }
    $url = rtrim($conf_base, '/') . '/send-sms';
    $payload = [
        'user_id' => $conf_user,
        'api_key' => $conf_key,
        'sender_id' => $conf_sender,
        'contact' => $to,
        'message' => $message,
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($errno === 0 && $http >= 200 && $http < 300);
    if ($conf_user === '' || $conf_key === '') {
        $ok = false;
        $error = 'SMS credentials not configured (sms_user_id/api_key)';
    }
    // Log summary for diagnostics (visible in Super Admin error log)
    error_log('[smslenz_send_sms] url=' . $url . ' http=' . ($http ?? 0) . ' errno=' . $errno . ' ok=' . ($ok ? '1' : '0') . ' err=' . (string)$error);
    try {
        $stmt = db()->prepare('INSERT INTO sms_logs (user_id, message, status) VALUES (?, ?, ?)');
        $uid = $_SESSION['user']['user_id'] ?? 0;
        $status = $ok ? 'sent' : 'failed';
        $stmt->bind_param('iss', $uid, $message, $status);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
    return ['ok' => $ok, 'http' => $http, 'errno' => $errno, 'error' => $error, 'body' => $response];
}

// Image helper: constrain image dimensions in-place (maintain aspect ratio)
if (!function_exists('resize_image_constrain')) {
    function resize_image_constrain(string $path, int $max_w, int $max_h): bool {
        try {
            if (!extension_loaded('gd')) { return false; }
            if (!is_file($path)) { return false; }
            $info = @getimagesize($path);
            if ($info === false) { return false; }
            $w = (int)($info[0] ?? 0); $h = (int)($info[1] ?? 0);
            if ($w <= 0 || $h <= 0) { return false; }
            if ($w <= $max_w && $h <= $max_h) { return true; } // already within bounds

            $ratio = min($max_w / $w, $max_h / $h);
            $nw = (int)max(1, floor($w * $ratio));
            $nh = (int)max(1, floor($h * $ratio));

            $mime = (string)($info['mime'] ?? '');
            $src = null;
            if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
                $src = @imagecreatefromjpeg($path);
            } elseif (strpos($mime, 'png') !== false) {
                $src = @imagecreatefrompng($path);
            } elseif (strpos($mime, 'gif') !== false) {
                $src = @imagecreatefromgif($path);
            } elseif (strpos($mime, 'webp') !== false && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($path);
            } else {
                $raw = @file_get_contents($path);
                if ($raw !== false) { $src = @imagecreatefromstring($raw); }
            }
            if (!$src) { return false; }

            $dst = @imagecreatetruecolor($nw, $nh);
            if (!$dst) { @imagedestroy($src); return false; }

            // Preserve transparency for PNG/GIF
            if (strpos($mime, 'png') !== false || strpos($mime, 'gif') !== false) {
                @imagealphablending($dst, false);
                @imagesavealpha($dst, true);
                $trans = @imagecolorallocatealpha($dst, 0, 0, 0, 127);
                if ($trans !== false) { @imagefilledrectangle($dst, 0, 0, $nw, $nh, $trans); }
            }

            @imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $ok = false;
            if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
                $ok = @imagejpeg($dst, $path, 85);
            } elseif (strpos($mime, 'png') !== false) {
                $ok = @imagepng($dst, $path, 6);
            } elseif (strpos($mime, 'gif') !== false) {
                $ok = @imagegif($dst, $path);
            } elseif (strpos($mime, 'webp') !== false && function_exists('imagewebp')) {
                $ok = @imagewebp($dst, $path, 80);
            } else {
                // Default to JPEG
                $ok = @imagejpeg($dst, $path, 85);
            }

            @imagedestroy($dst);
            @imagedestroy($src);
            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!headers_sent()) {
    $__li = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    $__uid = (int)($_SESSION['user']['user_id'] ?? 0);
    $__sessRole = (string)($_SESSION['role'] ?? '');
    if ($__li && $__uid > 0 && $__sessRole !== '') {
        try {
            $__st = db()->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
            $__st->bind_param('i', $__uid);
            $__st->execute();
            $__res = $__st->get_result();
            $__row = $__res->fetch_assoc();
            $__st->close();
            $__dbRole = (string)($__row['role'] ?? '');
            if ($__dbRole !== '' && strcasecmp($__dbRole, $__sessRole) !== 0) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                @session_destroy();
                $msg = 'Your account is now ' . strtoupper($__dbRole) . '. Please sign in again.';
                redirect_with_message(rtrim($base_url, '/') . '/auth/login.php', $msg, 'info');
            }
        } catch (Throwable $__e) {}
    }
}
