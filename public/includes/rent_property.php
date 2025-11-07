<?php
 $pid = (int)($_GET['id'] ?? 0);
 http_response_code(302);
 header('Location: /rentallanka/public/includes/view_property.php?id=' . $pid);
 exit;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/auth_guard.php'; require_role('customer');

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch property (only available)
$sql = 'SELECT p.*, u.name AS owner_name
        FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id
        WHERE p.property_id = ? AND p.status = "available" LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $pid);
$stmt->execute();
$prop = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$prop) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">Property not found or not available.</div></div></body></html>';
  exit;
}

// Gallery (primary first)
$gallery = [];
$gp = db()->prepare('SELECT image_id, image_path, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id DESC');
$gp->bind_param('i', $pid);
$gp->execute();
$rgp = $gp->get_result();
while ($row = $rgp->fetch_assoc()) { $gallery[] = $row; }
$gp->close();

function norm_url($p) {
  if (!$p) return '';
  if (preg_match('#^https?://#i', $p)) return $p;
  return '/' . ltrim($p, '/');
}

// Handle POST (add to cart) for monthly property rental
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $start = trim($_POST['start_date'] ?? '');
  $end = trim($_POST['end_date'] ?? '');
  $cust_id = (int)($_SESSION['user']['user_id'] ?? 0);
  if (!$cust_id || !$prop) {
    redirect_with_message($GLOBALS['base_url'] . '/index.php', 'Invalid request', 'error');
  }
  if (!$start || !$end || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || strtotime($end) <= strtotime($start)) {
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/rent_property.php?id=' . (int)$pid, 'Invalid dates', 'error');
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
  // calculate months (ceil days/30)
  $days = (int)max(1, round((strtotime($end) - strtotime($start)) / 86400));
  $months = (int)max(1, ceil($days / 30));
  $price = (float)$prop['price_per_month'];
  $total = $price * $months;
  // insert cart item
  $it = db()->prepare('INSERT INTO cart_items (cart_id, room_id, property_id, item_type, start_date, end_date, quantity, meal_plan, price, total_price) VALUES (?, NULL, ?, "monthly_property", ?, ?, 1, "none", ?, ?)');
  $it->bind_param('iissdd', $cart_id, $pid, $start, $end, $price, $total);
  if ($it->execute()) {
    $it->close();
    redirect_with_message($GLOBALS['base_url'] . '/index.php', 'Added property to cart', 'success');
  } else {
    $it->close();
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/rent_property.php?id=' . (int)$pid, 'Failed to add to cart', 'error');
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rent: <?php echo htmlspecialchars($prop['title']); ?> - Property</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card mb-3">
        <div class="card-header">Images</div>
        <div class="card-body">
          <?php $primaryUrl = norm_url($prop['image'] ?? ''); ?>
          <?php if ($primaryUrl): ?>
            <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="">
          <?php endif; ?>
          <?php if ($gallery): ?>
            <div class="row g-2">
              <?php foreach ($gallery as $img): ?>
                <?php $p = norm_url($img['image_path'] ?? ''); ?>
                <div class="col-6 col-md-4">
                  <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="">
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$primaryUrl): ?>
            <div class="text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header">Property Details</div>
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo htmlspecialchars($prop['title']); ?></h1>
          <dl class="row mb-0">
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Price / month</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$prop['price_per_month'], 2); ?></dd>
            <dt class="col-sm-4">Bedrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bedrooms']; ?></dd>
            <dt class="col-sm-4">Bathrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bathrooms']; ?></dd>
            <dt class="col-sm-4">Living rooms</dt><dd class="col-sm-8"><?php echo (int)$prop['living_rooms']; ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['property_type'] ?? ''); ?></dd>
          </dl>
          <div class="mt-3">
            <div class="fw-semibold mb-1">Description</div>
            <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($prop['description'] ?? '')); ?></div>
          </div>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Rent this property</div>
        <div class="card-body">
          <form method="post" action="#" class="row g-3">
            <div class="col-12">
              <label class="form-label">Start date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">End date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Continue</button>
              <span class="text-muted ms-2">(Form submission wiring pending)</span>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
