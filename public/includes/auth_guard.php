<?php
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
