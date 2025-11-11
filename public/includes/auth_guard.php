<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once __DIR__ . '/../../config/config.php';

function require_login(): void {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in first', 'error');
    }
}

function require_role(string $role): void {
    require_login();
    $current = $_SESSION['role'] ?? '';
    if ($current !== $role) {
        redirect_with_message($GLOBALS['base_url'] . '/index.php', 'Unauthorized', 'error');
    }
}

function require_super_admin(): void {
    if (!isset($_SESSION['super_admin_id']) || (int)$_SESSION['super_admin_id'] <= 0) {
        redirect_with_message($GLOBALS['base_url'] . '/superAdmin/login.php', 'Super admin login required', 'error');
    }
}
