<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');

$allowed_status = ['pending','available','unavailable','rented'];
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$rv_error = '';
$rv_ok = '';

$rid = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $rv_error = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
      $new_status = $_POST['status'] ?? '';
      if ($rid <= 0 || !in_array($new_status, $allowed_status, true)) {
        $rv_error = 'Bad input';
      } else {
        $st = db()->prepare('UPDATE rooms SET status = ? WHERE room_id = ?');
        $st->bind_param('si', $new_status, $rid);
        if ($st->execute()) { $rv_ok = 'Status updated'; } else { $rv_error = 'Update failed'; }
        $st->close();
      }
    } elseif ($action === 'delete') {
      if ($rid > 0) {
        $st = db()->prepare('DELETE FROM rooms WHERE room_id = ?');
        $st->bind_param('i', $rid);
        if ($st->execute() && $st->affected_rows > 0) {
          redirect_with_message('room_management.php', 'Room deleted', 'success');
        } else { $rv_error = 'Delete failed'; }
        $st->close();
      } else { $rv_error = 'Bad input'; }
    }
  }
}
if ($rid <= 0) {
  redirect_with_message('room_management.php', 'Invalid room ID', 'error');
}

// Fetch room details + owner fields
$sql = 'SELECT r.*, 
               u.user_id AS owner_id,
               u.username AS owner_name,
               u.email   AS owner_email,
               u.profile_image AS owner_profile_image,
               u.phone   AS owner_phone,
               u.role    AS owner_role,
               u.status  AS owner_status,
               u.created_at AS owner_created_at
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id
        WHERE r.room_id = ? LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $rid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
  redirect_with_message('room_management.php', 'Room not found', 'error');
}

// Fetch room images (primary first)
$images = [];
$si = db()->prepare('SELECT image_id, image_path, is_primary, uploaded_at FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id DESC');
$si->bind_param('i', $rid);
$si->execute();
$rsi = $si->get_result();
while ($row = $rsi->fetch_assoc()) { $images[] = $row; }
$si->close();

// Fetch payment slips
$slips = [];
$sp = db()->prepare('SELECT slip_id, slip_path, uploaded_at FROM room_payment_slips WHERE room_id = ? ORDER BY slip_id DESC');
$sp->bind_param('i', $rid);
$sp->execute();
$rsp = $sp->get_result();
while ($row = $rsp->fetch_assoc()) { $slips[] = $row; }
$sp->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Room #<?php echo (int)$room['room_id']; ?> - Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Room Details</h1>
      <div class="d-flex align-items-center gap-2">
        <form method="post" class="d-flex align-items-center gap-2">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="update_status">
          <select name="status" class="form-select form-select-sm" required>
            <?php foreach ($allowed_status as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($room['status']??'')===$s?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary" type="submit">Update</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this room?');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
        </form>
        <a class="btn btn-outline-secondary btn-sm" href="room_management.php">Back</a>
      </div>
    </div>
    <?php if ($rv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($rv_error); ?></div><?php endif; ?>
    <?php if ($rv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($rv_ok); ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">Overview</div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">ID</dt><dd class="col-sm-8"><?php echo (int)$room['room_id']; ?></dd>
              <dt class="col-sm-4">Title</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['title'] ?? ''); ?></dd>
              <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars(($room['owner_name'] ?? 'N/A') . ' (#' . (int)($room['owner_id'] ?? 0) . ')'); ?></dd>
              <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></dd>
              <dt class="col-sm-4">Status</dt><dd class="col-sm-8 text-uppercase"><span class="badge bg-secondary"><?php echo htmlspecialchars($room['status'] ?? ''); ?></span></dd>
              <dt class="col-sm-4">Beds</dt><dd class="col-sm-8"><?php echo (int)($room['beds'] ?? 0); ?></dd>
              <dt class="col-sm-4">Price / day</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
              <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['created_at'] ?? ''); ?></dd>
            </dl>
            <div class="mt-3">
              <div class="fw-semibold mb-1">Description</div>
              <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($room['description'] ?? '')); ?></div>
            </div>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">Owner Details</div>
          <div class="card-body">
            <?php if (!empty($room['owner_profile_image'])): ?>
              <?php
                $opi = (string)$room['owner_profile_image'];
                if (!preg_match('#^https?://#i', $opi)) {
                  $opi = rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($opi, '/');
                }
              ?>
              <div class="float-end ms-3 mb-3">
                <img src="<?php echo htmlspecialchars($opi); ?>" alt="Owner profile" class="rounded-circle border" style="width:96px;height:96px;object-fit:cover;">
              </div>
            <?php endif; ?>
            <dl class="row mb-0">
              <dt class="col-sm-4">Owner ID</dt><dd class="col-sm-8"><?php echo (int)($room['owner_id'] ?? 0); ?></dd>
              <dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_name'] ?? 'N/A'); ?></dd>
              <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_email'] ?? ''); ?></dd>
              <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_phone'] ?? ''); ?></dd>
              <dt class="col-sm-4">Role</dt><dd class="col-sm-8 text-uppercase"><?php echo htmlspecialchars($room['owner_role'] ?? ''); ?></dd>
              <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-secondary"><?php echo htmlspecialchars($room['owner_status'] ?? ''); ?></span></dd>
              <dt class="col-sm-4">User Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_created_at'] ?? ''); ?></dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="card mb-3">
          <div class="card-header" id="slips">Payment Slips</div>
          <div class="list-group list-group-flush">
            <?php foreach ($slips as $s): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  #<?php echo (int)$s['slip_id']; ?>
                  <span class="text-muted">(<?php echo htmlspecialchars($s['uploaded_at']); ?>)</span>
                </div>
                <?php 
                  $sp = $s['slip_path'] ?? ''; 
                  if ($sp && !preg_match('#^https?://#i', $sp)) { $sp = rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($sp, '/'); }
                ?>
                <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars($sp); ?>">Open</a>
              </div>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
              <div class="list-group-item text-muted">No slips attached.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-header">Image</div>
          <div class="card-body">
            <?php if ($images): ?>
              <?php 
                $primary = $images[0];
                $primaryUrl = $primary['image_path'] ?? '';
                if ($primaryUrl && !preg_match('#^https?://#i', $primaryUrl)) { $primaryUrl = rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($primaryUrl, '/'); }
              ?>
              <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="">
              <?php if (count($images) > 1): ?>
                <div class="row g-2">
                  <?php foreach (array_slice($images, 1) as $img): ?>
                    <?php 
                      $ip = $img['image_path'] ?? ''; 
                      if ($ip && !preg_match('#^https?://#i', $ip)) { $ip = rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($ip, '/'); }
                    ?>
                    <div class="col-6 col-md-4">
                      <a href="<?php echo htmlspecialchars($ip); ?>" target="_blank">
                        <img src="<?php echo htmlspecialchars($ip); ?>" class="img-fluid rounded" alt="">
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-muted">No images uploaded.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
