<?php
require_once __DIR__ . '/../config/config.php';

if (!headers_sent()) { header('Content-Type: application/json'); }
// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function json_ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit; }
function json_err($msg = 'Bad request', $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// Auth
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$loggedIn) { json_err('Unauthorized', 401); }
$role = $_SESSION['role'] ?? '';
$current_user_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($current_user_id <= 0) { json_err('Unauthorized', 401); }

// Route: unread count for badge (customer sees own inbox; admin also sees their own inbox)
if ($action === 'count_unread') {
    if (!in_array($role, ['admin','customer'], true)) { json_err('Forbidden', 403); }
    $uid = $current_user_id;
    $st = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    if ($st === false) { json_err('Query prepare failed', 500); }
    $st->bind_param('i', $uid);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : ['c' => 0];
    $st->close();
    json_ok(['unread' => (int)($row['c'] ?? 0)]);
}

// Route: fetch CSRF token
if ($action === 'csrf') {
    json_ok(['csrf_token' => $_SESSION['csrf_token']]);
}

// Route: list notifications
// - Customer: list own notifications
// - Admin: list notifications for a specific customer_id (required)
if ($action === 'list') {
    $customer_id = (int)($_GET['customer_id'] ?? 0);
    $unread_only = (int)($_GET['unread_only'] ?? 0) === 1;
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

    $params = '';
    $bind = [];
    $where = [];

    if ($role === 'admin') {
        if ($customer_id <= 0) { json_err('customer_id required for admin', 422); }
        $where[] = 'user_id = ?';
        $params .= 'i';
        $bind[] = $customer_id;
    } elseif ($role === 'customer') {
        $where[] = 'user_id = ?';
        $params .= 'i';
        $bind[] = $current_user_id;
    } else {
        json_err('Forbidden', 403);
    }

    if ($unread_only) { $where[] = 'is_read = 0'; }

    $sql = 'SELECT notification_id, user_id, title, message, type, property_id, is_read, read_at, created_at
            FROM notifications
            ' . ($where ? ('WHERE ' . implode(' AND ', $where)) : '') . '
            ORDER BY notification_id DESC
            LIMIT ' . $limit;

    $out = [];
    if ($params !== '') {
        $st = db()->prepare($sql);
        if ($st === false) { json_err('Query prepare failed', 500); }
        $st->bind_param($params, ...$bind);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $out[] = $r; }
        $st->close();
    } else {
        $res = db()->query($sql);
        if ($res) { while ($r = $res->fetch_assoc()) { $out[] = $r; } }
    }
    json_ok(['items' => $out]);
}

// Route: customer -> admin message (generic or advertiser-related)
// POST: action=send_to_admin, title, message, csrf_token
if ($action === 'send_to_admin') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'customer') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    $title = trim($_POST['title'] ?? 'Message from Customer');
    $message = trim($_POST['message'] ?? '');
    if ($message === '') { json_err('message required', 422); }

    // Pick any admin recipient
    $res = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
    $admin_id = 0;
    if ($res && ($row = $res->fetch_assoc())) { $admin_id = (int)$row['user_id']; }
    if ($admin_id <= 0) { json_err('No admin available', 500); }

    $type = 'system';
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    $stmt->bind_param('isss', $admin_id, $title, $message, $type);
    $ok = $stmt->execute();
    $nid = $ok ? (int)$stmt->insert_id : 0;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) { json_err('Insert failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Route: admin -> customer message
// POST: action=send_to_customer, customer_id, title, message, csrf_token
if ($action === 'send_to_customer') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'admin') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Message from Admin');
    $message = trim($_POST['message'] ?? '');
    if ($customer_id <= 0 || $message === '') { json_err('customer_id and message required', 422); }

    // Validate recipient exists and is customer
    $chk = db()->prepare("SELECT user_id FROM users WHERE user_id=? AND role='customer' LIMIT 1");
    $chk->bind_param('i', $customer_id);
    $chk->execute();
    $r = $chk->get_result();
    $exists = (bool)$r->fetch_row();
    $chk->close();
    if (!$exists) { json_err('Customer not found', 404); }

    $type = 'system';
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    $stmt->bind_param('isss', $customer_id, $title, $message, $type);
    $ok = $stmt->execute();
    $nid = $ok ? (int)$stmt->insert_id : 0;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) { json_err('Insert failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Route: customer requests "As an Advertiser" upgrade
// POST: action=request_advertiser, csrf_token
if ($action === 'request_advertiser') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'customer') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    // Avoid duplicate pending requests
    $check = db()->prepare("SELECT request_id FROM advertiser_requests WHERE user_id=? AND status='pending' LIMIT 1");
    $check->bind_param('i', $current_user_id);
    $check->execute();
    $rs = $check->get_result();
    $exists = (bool)$rs->fetch_row();
    $check->close();
    if ($exists) { json_ok(['message' => 'You already have a pending request']); }

    $st = db()->prepare("INSERT INTO advertiser_requests (user_id, status) VALUES (?, 'pending')");
    $st->bind_param('i', $current_user_id);
    $ok = $st->execute();
    $rid = $ok ? (int)$st->insert_id : 0;
    $err = $st->error;
    $st->close();
    if (!$ok) { json_err('Could not create request: ' . $err, 500); }

    // Notify first admin (optional)
    $res = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
    $admin_id = 0;
    if ($res && ($row = $res->fetch_assoc())) { $admin_id = (int)$row['user_id']; }
    if ($admin_id > 0) {
        $title = 'New Advertiser Request';
        $message = 'Customer #' . $current_user_id . ' requested to become an advertiser (Request #' . $rid . ').';
        $type = 'system';
        $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
        $stmt->bind_param('isss', $admin_id, $title, $message, $type);
        $stmt->execute();
        $stmt->close();
    }

    json_ok(['request_id' => $rid]);
}

// Route: mark as read
// POST: action=mark_read, notification_id, csrf_token
if ($action === 'mark_read') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }
    $nid = (int)($_POST['notification_id'] ?? 0);
    if ($nid <= 0) { json_err('notification_id required', 422); }

    // Ensure allowed
    $st = db()->prepare('SELECT user_id FROM notifications WHERE notification_id=?');
    $st->bind_param('i', $nid);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    $st->close();
    if (!$row) { json_err('Not found', 404); }
    $target_user_id = (int)$row['user_id'];

    if ($role === 'admin') {
        // Admin can mark any
    } else if ($role === 'customer') {
        if ($target_user_id !== $current_user_id) { json_err('Forbidden', 403); }
    } else { json_err('Forbidden', 403); }

    $up = db()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?');
    $up->bind_param('i', $nid);
    $ok = $up->execute();
    $err = $up->error;
    $up->close();
    if (!$ok) { json_err('Update failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Route: delete notification
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

    if ($role === 'admin') {
        // Admin can delete any
    } else if ($role === 'customer') {
        if ($target_user_id !== $current_user_id) { json_err('Forbidden', 403); }
    } else { json_err('Forbidden', 403); }

    $del = db()->prepare('DELETE FROM notifications WHERE notification_id=?');
    $del->bind_param('i', $nid);
    $ok = $del->execute();
    $err = $del->error;
    $del->close();
    if (!$ok) { json_err('Delete failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

json_err('Unknown action', 400);
