<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/auth_guard.php'; require_role('customer');

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch room (only available)
$sql = 'SELECT r.*, u.name AS owner_name
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id
        WHERE r.room_id = ? AND r.status = "available" LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $rid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">Room not found or not available.</div></div></body></html>';
  exit;
}

// Gallery (primary first)
$images = [];
$si = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id DESC');
$si->bind_param('i', $rid);
$si->execute();
$rsi = $si->get_result();
while ($row = $rsi->fetch_assoc()) { $images[] = $row; }
$si->close();

function norm_url($p) {
  if (!$p) return '';
  if (preg_match('#^https?://#i', $p)) return $p;
  return '/' . ltrim($p, '/');
}

// Handle POST (add to cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $start = trim($_POST['start_date'] ?? '');
  $end = trim($_POST['end_date'] ?? '');
  $cust_id = (int)($_SESSION['user']['user_id'] ?? 0);
  if (!$cust_id || !$room) {
    redirect_with_message($GLOBALS['base_url'] . '/index.php', 'Invalid request', 'error');
  }
  if (!$start || !$end || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || strtotime($end) < strtotime($start)) {
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/rent_room.php?id=' . (int)$rid, 'Invalid dates', 'error');
  }
  // ensure cart
  $cart_id = 0;
  $c = db()->prepare('SELECT cart_id FROM carts WHERE customer_id=? AND status="active" LIMIT 1');
  $c->bind_param('i', $cust_id);
  $c->execute();
  $cres = $c->get_result()->fetch_assoc();
  $c->close();
  if ($cres) { $cart_id = (int)$cres['cart_id']; }
  if ($cart_id <= 0) {
    $ci = db()->prepare('INSERT INTO carts (customer_id, status) VALUES (?, "active")');
    $ci->bind_param('i', $cust_id);
    $ci->execute();
    $cart_id = (int)db()->insert_id;
    $ci->close();
  }
  // calculate totals
  $days = (int)max(1, round((strtotime($end) - strtotime($start)) / 86400));
  $price = (float)$room['price_per_day'];
  $total = $price * $days;
  // insert cart item
  $it = db()->prepare('INSERT INTO cart_items (cart_id, room_id, property_id, item_type, start_date, end_date, quantity, meal_plan, price, total_price) VALUES (?, ?, NULL, "daily_room", ?, ?, 1, "none", ?, ?)');
  $it->bind_param('iissdd', $cart_id, $rid, $start, $end, $price, $total);
  if ($it->execute()) {
    $it->close();
    redirect_with_message($GLOBALS['base_url'] . '/index.php', 'Added room to cart', 'success');
  } else {
    $it->close();
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/rent_room.php?id=' . (int)$rid, 'Failed to add to cart', 'error');
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rent: <?php echo htmlspecialchars($room['title']); ?> - Room</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Room Details</div>
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo htmlspecialchars($room['title']); ?></h1>
          <dl class="row mb-0">
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></dd>
            <dt class="col-sm-4">Beds</dt><dd class="col-sm-8"><?php echo (int)$room['beds']; ?></dd>
            <dt class="col-sm-4">Price / day</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
          </dl>
          <div class="mt-3">
            <div class="fw-semibold mb-1">Description</div>
            <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($room['description'] ?? '')); ?></div>
          </div>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Rent this room</div>
        <div class="card-body">
          <form method="post" action="#" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Start date</label>
              <input type="date" name="start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">End date</label>
              <input type="date" name="end_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
              <div class="form-text">Same-day stay is allowed.</div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Continue</button>
              <span class="text-muted ms-2">(Form submission wiring pending)</span>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card mb-3">
        <div class="card-header">Images</div>
        <div class="card-body">
          <?php if ($images): ?>
            <?php $primaryUrl = norm_url($images[0]['image_path'] ?? ''); ?>
            <?php if ($primaryUrl): ?>
              <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="">
            <?php endif; ?>
            <?php if (count($images) > 1): ?>
              <div class="row g-2">
                <?php foreach (array_slice($images, 1) as $img): ?>
                  <?php $p = norm_url($img['image_path'] ?? ''); ?>
                  <div class="col-6 col-md-4">
                    <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                      <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="">
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
<?php require_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
