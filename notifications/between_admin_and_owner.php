<?php
require_once __DIR__ . '/../config/config.php';

// JSON output only
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Ensure a CSRF token exists for the session (to be used by POST requests)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Helpers
function json_ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit; }
function json_err($msg = 'Bad request', $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// Auth helpers based on existing session pattern
$role = $_SESSION['role'] ?? '';
$current_user_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($current_user_id <= 0) { json_err('Unauthorized', 401); }

// Utility: find an admin user id to receive owner->admin messages (fallback to smallest admin id)
function get_any_admin_id(): int {
    try {
        $res = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) { return (int)$row['user_id']; }
    } catch (Throwable $e) {}
    return 0;
}

// Route: fetch CSRF token for client apps
if ($action === 'csrf') {
    json_ok(['csrf_token' => $_SESSION['csrf_token']]);
}

// Route: list notifications
// - Owner: lists own notifications (optionally unread only)
// - Admin: lists notifications sent to a specific owner_id
// - Customer: lists own notifications
if ($action === 'list') {
    $owner_id = (int)($_GET['owner_id'] ?? 0);
    $unread_only = (int)($_GET['unread_only'] ?? 0) === 1;
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

    $params = '';
    $bind = [];
    $where = [];

    if ($role === 'admin') {
        if ($owner_id > 0) {
            $where[] = 'user_id = ?';
            $params .= 'i';
            $bind[] = $owner_id; // messages addressed to the owner
        } else {
            // Admin inbox: notifications addressed to the current admin
            $where[] = 'user_id = ?';
            $params .= 'i';
            $bind[] = $current_user_id;
        }
    } else if ($role === 'owner') {
        // Owners can only view their own notifications
        $where[] = 'user_id = ?';
        $params .= 'i';
        $bind[] = $current_user_id;
    } else if ($role === 'customer') {
        // Customers can view their own notifications
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

// Route: send admin -> owner notification
// POST: action=send_to_owner, owner_id, title, message, csrf_token
if ($action === 'send_to_owner') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'admin') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }

    $owner_id = (int)($_POST['owner_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Message from Admin');
    $message = trim($_POST['message'] ?? '');
    if ($owner_id <= 0 || $message === '') { json_err('owner_id and message required', 422); }

    // validate recipient exists and is owner
    $chk = db()->prepare("SELECT user_id FROM users WHERE user_id=? AND role='owner' LIMIT 1");
    $chk->bind_param('i', $owner_id);
    $chk->execute();
    $res = $chk->get_result();
    $exists = (bool)$res->fetch_row();
    $chk->close();
    if (!$exists) { json_err('Owner not found', 404); }

    $type = 'system';
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    $stmt->bind_param('isss', $owner_id, $title, $message, $type);
    $ok = $stmt->execute();
    $nid = $ok ? (int)$stmt->insert_id : 0;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) { json_err('Insert failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Route: send owner -> admin notification (optional)
// POST: action=send_to_admin, title, message, csrf_token
if ($action === 'send_to_admin') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    if ($role !== 'owner') { json_err('Forbidden', 403); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }
    $title = trim($_POST['title'] ?? 'Message from Owner');
    $message = trim($_POST['message'] ?? '');
    if ($message === '') { json_err('message required', 422); }

    $admin_id = get_any_admin_id();
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

// Route: mark as read (owner/customer can mark own notifications, admin can mark any)
// POST: action=mark_read, notification_id, csrf_token
if ($action === 'mark_read') {
    if ($method !== 'POST') { json_err('Method not allowed', 405); }
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) { json_err('Invalid CSRF token', 403); }
    $nid = (int)($_POST['notification_id'] ?? 0);
    if ($nid <= 0) { json_err('notification_id required', 422); }

    // Ensure the caller is allowed to update this notification
    $st = db()->prepare('SELECT user_id FROM notifications WHERE notification_id=?');
    $st->bind_param('i', $nid);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    $st->close();
    if (!$row) { json_err('Not found', 404); }
    $owner_user_id = (int)$row['user_id'];

    if ($role === 'owner' || $role === 'customer') {
        if ($owner_user_id !== $current_user_id) { json_err('Forbidden', 403); }
    } elseif ($role === 'admin') {
        // Admins can mark any notification, including those addressed to themselves
        // No additional restriction
    } else {
        json_err('Forbidden', 403);
    }

    $up = db()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?');
    $up->bind_param('i', $nid);
    $ok = $up->execute();
    $err = $up->error;
    $up->close();
    if (!$ok) { json_err('Update failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

// Route: delete notification (owner/customer can delete own, admin can delete any)
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
    $owner_user_id = (int)$row['user_id'];

    if ($role === 'owner' || $role === 'customer') {
        if ($owner_user_id !== $current_user_id) { json_err('Forbidden', 403); }
    } elseif ($role === 'admin') {
    } else {
        json_err('Forbidden', 403);
    }

    $del = db()->prepare('DELETE FROM notifications WHERE notification_id=?');
    $del->bind_param('i', $nid);
    $ok = $del->execute();
    $err = $del->error;
    $del->close();
    if (!$ok) { json_err('Delete failed: ' . $err, 500); }
    json_ok(['notification_id' => $nid]);
}

json_err('Unknown action', 400);

