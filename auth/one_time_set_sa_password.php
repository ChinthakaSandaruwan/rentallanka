<?php
require_once __DIR__ . '/../config/config.php';

$allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1']);
if (!$allowed) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$name = 'superadmin';
$newPassword = 'SuperAdmin';

// Ensure row exists
$stmt = db()->prepare('SELECT super_admin_id FROM super_admins WHERE name = ? LIMIT 1');
$stmt->bind_param('s', $name);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo 'Super admin user not found. Please create it first in DB (name=superadmin).';
    exit;
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = db()->prepare('UPDATE super_admins SET password_hash = ?, status = "active" WHERE name = ? LIMIT 1');
$stmt->bind_param('ss', $hash, $name);
$ok = $stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

if ($ok && $aff >= 0) {
    echo 'OK: Password for superadmin set to requested value. You can now log in using the new password and OTP.<br>';
    echo 'Next: DELETE this file: auth/one_time_set_sa_password.php for security.';
} else {
    echo 'Failed to update password.';
}
