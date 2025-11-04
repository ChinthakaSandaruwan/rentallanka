<?php
require_once __DIR__ . '/../config/config.php';
unset($_SESSION['super_admin_id']);
if (($_SESSION['role'] ?? '') === 'super_admin') {
    unset($_SESSION['role']);
}
redirect_with_message($base_url . '/index.php', 'Super admin logged out');
