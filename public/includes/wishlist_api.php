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

header('Content-Type: application/json');

// Ensure logged in (customers and owners can use wishlist; adjust if needed)
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$userArr = $_SESSION['user'] ?? null;
$userId = $userArr['user_id'] ?? 0;
if (!$loggedIn || $userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please log in first.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$type = strtolower(trim($_GET['type'] ?? $_POST['type'] ?? 'property'));
$propertyId = (int)($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
$roomId = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);

if (!in_array($action, ['add', 'remove', 'status'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
if ($type !== 'property' && $type !== 'room') { $type = 'property'; }

// Normalize target id based on type
$targetId = $type === 'room' ? $roomId : $propertyId;
if ($targetId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid target']);
    exit;
}

// Optional: verify target exists
$exists = false;
try {
    if ($type === 'room') {
        $stmt = db()->prepare('SELECT room_id FROM rooms WHERE room_id = ? LIMIT 1');
    } else {
        $stmt = db()->prepare('SELECT property_id FROM properties WHERE property_id = ? LIMIT 1');
    }
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {
}
if (!$exists) {
    echo json_encode(['status' => 'error', 'message' => ($type === 'room' ? 'Room not found' : 'Property not found')]);
    exit;
}

if ($action === 'status') {
    if ($type === 'room') {
        $stmt = db()->prepare('SELECT wishlist_id FROM room_wishlist WHERE customer_id = ? AND room_id = ?');
    } else {
        $stmt = db()->prepare('SELECT wishlist_id FROM wishlist WHERE customer_id = ? AND property_id = ?');
    }
    $stmt->bind_param('ii', $userId, $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $inList = $res->num_rows > 0;
    $stmt->close();
    echo json_encode(['status' => 'ok', 'in_wishlist' => $inList]);
    exit;
}

if ($action === 'add') {
    // Avoid duplicate insert relying on unique key
    if ($type === 'room') {
        $stmt = db()->prepare('INSERT INTO room_wishlist (customer_id, room_id) VALUES (?, ?)');
    } else {
        $stmt = db()->prepare('INSERT INTO wishlist (customer_id, property_id) VALUES (?, ?)');
    }
    $stmt->bind_param('ii', $userId, $targetId);
    $ok = $stmt->execute();
    $errno = db()->errno;
    $stmt->close();
    if ($ok) {
        echo json_encode(['status' => 'success', 'message' => 'Added to wishlist.']);
    } else {
        // 1062 = duplicate key (already exists)
        if ((int)$errno === 1062) {
            echo json_encode(['status' => 'exists', 'message' => 'Already in wishlist.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong.']);
        }
    }
    exit;
}

if ($action === 'remove') {
    if ($type === 'room') {
        $stmt = db()->prepare('DELETE FROM room_wishlist WHERE customer_id = ? AND room_id = ?');
    } else {
        $stmt = db()->prepare('DELETE FROM wishlist WHERE customer_id = ? AND property_id = ?');
    }
    $stmt->bind_param('ii', $userId, $targetId);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Removed from wishlist.', 'removed' => $aff > 0]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unhandled']);
