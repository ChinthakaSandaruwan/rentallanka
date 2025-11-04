<?php
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
define('DB_PASS', '');
define('DB_NAME', 'sri_lanka_rental_system');

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
