<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('customer');
require_once __DIR__ . '/../config/config.php';

$customer_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($customer_id <= 0) {
  redirect_with_message('../auth/login.php', 'Please login', 'error');
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';

// Handle slip upload for a specific active room rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_slip') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $room_rental_id = (int)($_POST['room_rental_id'] ?? 0);
    if ($room_id <= 0 || $room_rental_id <= 0) {
      $err = 'Bad input';
    } elseif (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
      $err = 'Please choose a file';
    } else {
      // Check rental belongs to this customer and is active
      $chk = db()->prepare('SELECT room_rental_id FROM room_rentals WHERE room_rental_id=? AND room_id=? AND customer_id=? AND status="active"');
      $chk->bind_param('iii', $room_rental_id, $room_id, $customer_id);
      $chk->execute();
      $has = $chk->get_result()->fetch_assoc();
      $chk->close();
      if (!$has) {
        $err = 'Rental not found or inactive';
      } else {
        $projectRoot = realpath(__DIR__ . '/..'); // project root
        $targetDir = $projectRoot . '/uploads/payment_slips/rooms';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
        $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $fname = 'room_slip_' . $room_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($safeExt ? ('.' . $safeExt) : '');
        $dest = $targetDir . '/' . $fname;
        if (move_uploaded_file($_FILES['slip']['tmp_name'], $dest)) {
          $url = rtrim($GLOBALS['base_url'], '/') . '/uploads/payment_slips/rooms/' . $fname;
          $ins = db()->prepare('INSERT INTO room_payment_slips (room_id, slip_path) VALUES (?, ?)');
          $ins->bind_param('is', $room_id, $url);
          if ($ins->execute()) { $ok = 'Slip uploaded'; } else { $err = 'Failed to save slip'; }
          $ins->close();
        } else {
          $err = 'Failed to upload file';
        }
      }
    }
  }
}

// Load customer's room rentals (active first)
$rows = [];
$sql = 'SELECT rr.room_rental_id, rr.room_id, rr.start_date, rr.end_date, rr.total_price, rr.status, rr.created_at, r.title
        FROM room_rentals rr
        JOIN rooms r ON r.room_id = rr.room_id
        WHERE rr.customer_id = ?
        ORDER BY (rr.status="active") DESC, rr.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$stmt->close();

// Fetch slips count per room for summary
$slipCounts = [];
if ($rows) {
  $roomIds = array_unique(array_map(fn($r) => (int)$r['room_id'], $rows));
  if ($roomIds) {
    $in = implode(',', array_fill(0, count($roomIds), '?'));
    $types = str_repeat('i', count($roomIds));
    $q = db()->prepare('SELECT room_id, COUNT(*) AS c FROM room_payment_slips WHERE room_id IN (' . $in . ') GROUP BY room_id');
    $q->bind_param($types, ...$roomIds);
    $q->execute();
    $rs = $q->get_result();
    while ($r = $rs->fetch_assoc()) { $slipCounts[(int)$r['room_id']] = (int)$r['c']; }
    $q->close();
  }
}

function norm_url($p) { if (!$p) return ''; if (preg_match('#^https?://#i', $p)) return $p; return '/' . ltrim($p, '/'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Room Daily Payments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">My Room Daily Payments</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Room</th>
              <th>Period</th>
              <th>Status</th>
              <th>Slips</th>
              <th class="text-end">Upload Slip</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): $isActive = ($r['status'] === 'active'); ?>
              <tr>
                <td><?php echo (int)$r['room_rental_id']; ?></td>
                <td><?php echo htmlspecialchars($r['title']); ?></td>
                <td><?php echo htmlspecialchars($r['start_date']); ?> → <?php echo htmlspecialchars($r['end_date']); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td>
                  <?php $cnt = $slipCounts[(int)$r['room_id']] ?? 0; ?>
                  <span class="badge bg-info text-dark"><?php echo (int)$cnt; ?></span>
                </td>
                <td class="text-end">
                  <?php if ($isActive): ?>
                    <form method="post" enctype="multipart/form-data" class="d-inline-flex gap-2 align-items-center">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="action" value="upload_slip">
                      <input type="hidden" name="room_id" value="<?php echo (int)$r['room_id']; ?>">
                      <input type="hidden" name="room_rental_id" value="<?php echo (int)$r['room_rental_id']; ?>">
                      <input class="form-control form-control-sm" type="file" name="slip" required>
                      <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="text-center py-4">No room rentals found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
