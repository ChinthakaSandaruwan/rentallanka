<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100;
    $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END);
      $pos = ftell($fp);
      $chunk = '';
      $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096);
        $read = $pos - $step;
        fseek($fp, $step);
        $chunk = fread($fp, $read) . $chunk;
        $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk);
      $slice = array_slice($parts, -$lines);
      $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $data;
    exit;
  }
}

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('admin');

$allowed_status = ['pending','available','unavailable','rented'];
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$rv_error = '';
$rv_ok = '';

$rid = (int)($_GET['id'] ?? 0);
// If no id provided, we'll render a list view
$list_mode = ($rid <= 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $rv_error = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    $targetRid = $rid > 0 ? $rid : (int)($_POST['id'] ?? 0);
    if ($action === 'update_status') {
      $new_status = $_POST['status'] ?? '';
      if ($targetRid <= 0 || !in_array($new_status, $allowed_status, true)) {
        $rv_error = 'Bad input';
      } else {
        $st = db()->prepare('UPDATE rooms SET status = ? WHERE room_id = ?');
        $st->bind_param('si', $new_status, $targetRid);
        if ($st->execute()) { $rv_ok = 'Status updated'; } else { $rv_error = 'Update failed'; }
        $st->close();
      }
    } elseif ($action === 'delete') {
      if ($targetRid > 0) {
        $st = db()->prepare('DELETE FROM rooms WHERE room_id = ?');
        $st->bind_param('i', $targetRid);
        if ($st->execute() && $st->affected_rows > 0) {
          // If in list mode, return to list; if in detail, also go to list after deletion
          redirect_with_message('room_view.php', 'Room deleted', 'success');
        } else { $rv_error = 'Delete failed'; }
        $st->close();
      } else { $rv_error = 'Bad input'; }
    }
  }
}
// Do not redirect when no id; instead render list view

// Fetch either details (when id provided) or list (when no id)
$room = null;
if (!$list_mode) {
  $sql = 'SELECT r.*, 
                 u.user_id AS owner_id,
                 u.name AS owner_name,
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
    redirect_with_message('room_view.php', 'Room not found', 'error');
  }
}

// When in detail mode, fetch images; when in list mode, fetch rooms collection
$images = [];
$slips = [];
if (!$list_mode) {
  $si = db()->prepare('SELECT image_id, image_path, is_primary, uploaded_at FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id DESC');
  $si->bind_param('i', $rid);
  $si->execute();
  $rsi = $si->get_result();
  while ($row = $rsi->fetch_assoc()) { $images[] = $row; }
  $si->close();
} else {
  $rooms = [];
  $q = db()->query('SELECT r.room_id, r.title, r.status, r.room_type, r.price_per_day, r.created_at, u.name AS owner_name FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id ORDER BY r.room_id DESC');
  if ($q) {
    while ($row = $q->fetch_assoc()) { $rooms[] = $row; }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo !$list_mode ? ('Room #'.(int)$room['room_id'].' - Details') : 'Rooms'; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
  <body>
    <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
    <div class="container py-4">
    <?php if (!$list_mode): ?>
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
          <a class="btn btn-outline-secondary btn-sm" href="room_view.php">Back</a>
        </div>
      </div>
      <?php if ($rv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($rv_error); ?></div><?php endif; ?>
      <?php if ($rv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($rv_ok); ?></div><?php endif; ?>

      <div class="row g-4">
        <div class="col-12 col-lg-7 order-lg-2">
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
                <dt class="col-sm-4">Max people</dt><dd class="col-sm-8"><?php echo (int)($room['total_people_count'] ?? 0); ?></dd>
                <dt class="col-sm-4">Price Per Night</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
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
                <dt class="col-sm-4">name</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_name'] ?? 'N/A'); ?></dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_email'] ?? ''); ?></dd>
                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_phone'] ?? ''); ?></dd>
                <dt class="col-sm-4">Role</dt><dd class="col-sm-8 text-uppercase"><?php echo htmlspecialchars($room['owner_role'] ?? ''); ?></dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-secondary"><?php echo htmlspecialchars($room['owner_status'] ?? ''); ?></span></dd>
                <dt class="col-sm-4">User Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_created_at'] ?? ''); ?></dd>
              </dl>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-5 order-lg-1">
          <div class="card">
            <div class="card-header">Images</div>
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
    <?php else: ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Rooms</h1>
        <a class="btn btn-outline-secondary btn-sm" href="../room.php">Admin Room Menu</a>
      </div>
      <?php if ($rv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($rv_error); ?></div><?php endif; ?>
      <?php if ($rv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($rv_ok); ?></div><?php endif; ?>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">ID</th>
                  <th>Title</th>
                  <th>Owner</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th class="text-end">Price/Day</th>
                  <th>Created</th>
                  <th style="width:110px;" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($rooms)): ?>
                  <?php foreach ($rooms as $r): ?>
                    <tr>
                      <td>#<?php echo (int)$r['room_id']; ?></td>
                      <td><?php echo htmlspecialchars($r['title'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($r['owner_name'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($r['room_type'] ?? ''); ?></td>
                      <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status'] ?? ''); ?></span></td>
                      <td class="text-end">LKR <?php echo number_format((float)($r['price_per_day'] ?? 0), 2); ?></td>
                      <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="room_view.php?id=<?php echo (int)$r['room_id']; ?>">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">No rooms found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
    </div>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script></body>
</html>
