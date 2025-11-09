 <?php
 require_once __DIR__ . '/../../public/includes/auth_guard.php';
 require_role('owner');
 require_once __DIR__ . '/../../config/config.php';

 $uid = (int)($_SESSION['user']['user_id'] ?? 0);
 if ($uid <= 0) {
   redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
 }

if (empty($_SESSION['csrf_room_status'])) {
  $_SESSION['csrf_room_status'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_room_status'];

$flash = '';
$flash_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($csrf, $token)) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Invalid request.', 'error');
    exit;
  }
  $room_id = (int)($_POST['room_id'] ?? 0);
  $new_status = strtolower((string)($_POST['status'] ?? ''));
  $allowed = ['available','unavailable']; // owners cannot directly set rented/pending
  if ($room_id > 0 && in_array($new_status, $allowed, true)) {
    // Fetch current status to prevent illegal transition from rented -> available
    $cur = db()->prepare('SELECT status FROM rooms WHERE room_id=? AND owner_id=?');
    $cur->bind_param('ii', $room_id, $uid);
    $cur->execute();
    $curRes = $cur->get_result()->fetch_assoc();
    $cur->close();
    $currentStatus = strtolower((string)($curRes['status'] ?? ''));
    // Allow changing to available even if currently rented; date overlap checks on booking enforce correctness.
    // Allow setting to available regardless of existing future rentals; overlap checks on booking will enforce availability per date.
    $st = db()->prepare('UPDATE rooms SET status=? WHERE room_id=? AND owner_id=?');
    $st->bind_param('sii', $new_status, $room_id, $uid);
    $ok = $st->execute();
    $aff = $st->affected_rows;
    $st->close();
    if ($ok && $aff > 0) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Status updated.', 'success');
      exit;
    }
    redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'No changes made. Make sure the room exists and belongs to you.', 'warning');
    exit;
  }
  redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Invalid input.', 'error');
  exit;
}

// Load owner's rooms
$rooms = [];
$activeMap = [];
try {
  $q = db()->prepare('SELECT room_id, title, status, price_per_day, created_at FROM rooms WHERE owner_id=? ORDER BY created_at DESC');
  $q->bind_param('i', $uid);
  $q->execute();
  $r = $q->get_result();
  while ($row = $r->fetch_assoc()) { $rooms[] = $row; }
  $q->close();
  // Build map of rooms that have active rentals (booked / checked_in)
  if ($rooms) {
    $ids = array_column($rooms, 'room_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // Prepare dynamic IN clause
    $types = str_repeat('i', count($ids));
    $sql = "SELECT room_id, COUNT(*) AS c FROM room_rents WHERE status IN ('booked','checked_in') AND room_id IN ($placeholders) GROUP BY room_id";
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $activeMap[(int)$row['room_id']] = (int)$row['c']; }
    $stmt->close();
  }
} catch (Throwable $e) { $rooms = []; $activeMap = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Room Status</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-toggles me-2"></i>Room Status</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if (!empty($flash)): ?>
    <?php $map = ['error'=>'danger','danger'=>'danger','success'=>'success','warning'=>'warning','info'=>'info']; $type = $map[$flash_type ?? 'info'] ?? 'info'; ?>
    <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($flash); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!$rooms): ?>
    <div class="alert alert-info">You have no rooms yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Current Status</th>
            <th>Price/Day</th>
            <th>Created</th>
            <th>Change To</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $rm): ?>
            <tr>
              <td>#<?php echo (int)$rm['room_id']; ?></td>
              <td><?php echo htmlspecialchars($rm['title'] ?: 'Untitled'); ?></td>
              <td>
                <?php $st = (string)($rm['status'] ?? ''); $cls = ['available'=>'success','rented'=>'warning','unavailable'=>'secondary','pending'=>'info'][$st] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st ?: 'pending'))); ?></span>
              </td>
              <td>LKR <?php echo number_format((float)($rm['price_per_day'] ?? 0), 2); ?></td>
              <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($rm['created_at'] ?? '')))); ?></td>
              <td>
                <?php $hasActive = !empty($activeMap[(int)$rm['room_id'] ?? 0]); ?>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="room_id" value="<?php echo (int)$rm['room_id']; ?>">
                  <select name="status" class="form-select form-select-sm" style="max-width: 180px;">
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                  </select>
                  <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </form>
              </td>
              <td>
                <a href="room_update.php?id=<?php echo (int)$rm['room_id']; ?>" class="btn btn-outline-secondary btn-sm">Edit Room</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
