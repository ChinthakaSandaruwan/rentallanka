<?php
require_once __DIR__ . '/security_bootstrap.php';
// PayHere Configuration
$merchant_id = "1224197"; // Replace with your Merchant ID
$merchant_secret = "Mzg1MDk0MTE4ODMyNDU4Mjc0OTMyNjI4MjI2Nzc3MzY5MTk2NzI4NQ=="; // Replace with your Merchant Secret

// URLs
$base_url = 'http://localhost/rentallanka';
$return_url = $base_url . "/payhere_recurring/success.php";
$cancel_url = $base_url . "/payhere_recurring/cancel.php";
$notify_url = $base_url . "/payhere_recurring/notify.php";

// Payment Mode (sandbox/live)
$payhere_url = "https://sandbox.payhere.lk/pay/checkout"; // Use live URL in production

// Database connection for XAMPP default setup
// Adjust credentials as needed for your environment
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '123321555');
define('DB_NAME', 'sri_lanka_rental_system');

$smslenz_user_id = getenv('SMSLENZ_USER_ID') ?: '';
$smslenz_api_key = getenv('SMSLENZ_API_KEY') ?: '';
$smslenz_sender_id = getenv('SMSLENZ_SENDER_ID') ?: 'SMSlenzDEMO';
$smslenz_base = 'https://smslenz.lk/api';

$__session_status = function_exists('session_status') ? session_status() : PHP_SESSION_NONE;
if ($__session_status === PHP_SESSION_NONE) {
    session_start();
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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

function smslenz_send_sms(string $to, string $message): array {
    global $smslenz_user_id, $smslenz_api_key, $smslenz_sender_id, $smslenz_base;
    $to = trim($to);
    if (!preg_match('/^\+94\d{9}$/', $to)) {
        return ['ok' => false, 'error' => 'Invalid phone format'];
    }
    $url = rtrim($smslenz_base, '/') . '/send-sms';
    $payload = [
        'user_id' => $smslenz_user_id,
        'api_key' => $smslenz_api_key,
        'sender_id' => $smslenz_sender_id,
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
    if (!$ok && $smslenz_user_id === '' && $smslenz_api_key === '') {
        $ok = true;
    }
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

