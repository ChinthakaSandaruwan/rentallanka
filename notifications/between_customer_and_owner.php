<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
    $f = ___DIR___ . '/../error/error.log';
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

require_once ___DIR___ . '/../config/config.php';

if (!headers_sent()) { header('Content-Type: application/json'); }

// Ensure CSRF token for POST actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function json_ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit; }
function json_err($msg = 'Bad request', $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$loggedIn) { json_err('Unauthorized', 401); }
$role = $_SESSION['role'] ?? '';
$current_user_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($current_user_id <= 0) { json_err('Unauthorized', 401); }

// Expose CSRF token
if ($action === 'csrf') {
    json_ok(['csrf_token' => $_SESSION['csrf_token']]);
}

// Unread count for badge (customer/owner sees own inbox)
if ($action === 'count_unread') {
    if (!in_array($role, ['customer','owner'], true)) { json_err('Forbidden', 403); }
    $st = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    if ($st === false) { json_err('Query prepare failed', 500); }
    $st->bind_param('i', $current_user_id);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : ['c' => 0];
    $st->close();
    json_ok(['unread' => (int)($row['c'] ?? 0)]);
}

// List notifications for current user
if ($action === 'list') {
    if (!in_array($role, ['customer','owner'], true)) { json_err('Forbidden', 403); }
    $unread_only = (int)($_GET['unread_only'] ?? 0) === 1;
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

    $where = ['user_id = ?'];
    $params = 'i';
    $bind = [$current_user_id];
    if ($unread_only) { $where[] = 'is_read = 0'; }

    $sql = 'SELECT notification_id, user_id, title, message, type, property_id, is_read, read_at, created_at
            FROM notifications
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY notification_id DESC
            LIMIT ' . $limit;
    $st = db()->prepare($sql);
    if ($st === false) { json_err('Query prepare failed', 500); }
    $st->bind_param($params, ...$bind);
    $st->execute();
    $rs = $st->get_result();
    $out = [];
    while ($r = $rs->fetch_assoc()) { $out[] = $r; }
    $st->close();
    json_ok(['items' => $out]);
}

// Customer -> Owner
// POST: action=send_to_owner, owner_id, title?, message, csrf_token
if ($action === 'send_to_owner') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'customer') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    $owner_id = (int)($_POST['owner_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Message from Customer');
    $message = trim($_POST['message'] ?? '');
    if ($owner_id <= 0 || $message === '') { json_err('owner_id and message required', 422); }

    // Validate target is owner
    $chk = db()->prepare("SELECT user_id FROM users WHERE user_id=? AND role='owner' LIMIT 1");
    $chk->bind_param('i', $owner_id);
    $chk->execute();
    $exists = (bool)$chk->get_result()->fetch_row();
    $chk->close();
    if (!$exists) { json_err('Owner not found', 404); }

    $type = 'system';
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    if ($stmt === false) { json_err('Insert prepare failed', 500); }
    $stmt->bind_param('isss', $owner_id, $title, $message, $type);
    $ok = $stmt->execute();
    $nid = $ok ? (int)$stmt->insert_id : 0;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) { json_err('Insert failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Owner -> Customer
// POST: action=send_to_customer, customer_id, title?, message, csrf_token
if ($action === 'send_to_customer') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'owner') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Message from Owner');
    $message = trim($_POST['message'] ?? '');
    if ($customer_id <= 0 || $message === '') { json_err('customer_id and message required', 422); }

    // Validate target is customer
    $chk = db()->prepare("SELECT user_id FROM users WHERE user_id=? AND role='customer' LIMIT 1");
    $chk->bind_param('i', $customer_id);
    $chk->execute();
    $exists = (bool)$chk->get_result()->fetch_row();
    $chk->close();
    if (!$exists) { json_err('Customer not found', 404); }

    $type = 'system';
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    if ($stmt === false) { json_err('Insert prepare failed', 500); }
    $stmt->bind_param('isss', $customer_id, $title, $message, $type);
    $ok = $stmt->execute();
    $nid = $ok ? (int)$stmt->insert_id : 0;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) { json_err('Insert failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Mark as read
// POST: action=mark_read, notification_id, csrf_token
if ($action === 'mark_read') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }
    $nid = (int)($_POST['notification_id'] ?? 0);
    if ($nid <= 0) { json_err('notification_id required', 422); }

    $st = db()->prepare('SELECT user_id FROM notifications WHERE notification_id=?');
    $st->bind_param('i', $nid);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    $st->close();
    if (!$row) { json_err('Not found', 404); }
    $target_user_id = (int)$row['user_id'];

    // Only recipient can update
    if ($target_user_id !== $current_user_id && !in_array($role, ['owner','customer'], true)) { json_err('Forbidden', 403); }

    $up = db()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?');
    $up->bind_param('i', $nid);
    $ok = $up->execute();
    $err = $up->error;
    $up->close();
    if (!$ok) { json_err('Update failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Delete
// POST: action=delete, notification_id, csrf_token
if ($action === 'delete') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }
    $nid = (int)($_POST['notification_id'] ?? 0);
    if ($nid <= 0) { json_err('notification_id required', 422); }

    $st = db()->prepare('SELECT user_id FROM notifications WHERE notification_id=?');
    $st->bind_param('i', $nid);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    $st->close();
    if (!$row) { json_err('Not found', 404); }
    $target_user_id = (int)$row['user_id'];

    if ($target_user_id !== $current_user_id && !in_array($role, ['owner','customer'], true)) { json_err('Forbidden', 403); }

    $del = db()->prepare('DELETE FROM notifications WHERE notification_id=?');
    $del->bind_param('i', $nid);
    $ok = $del->execute();
    $err = $del->error;
    $del->close();
    if (!$ok) { json_err('Delete failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

json_err('Unknown action', 400);
