<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/auth_guard.php'; require_role('customer');

$customer_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($customer_id <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please login', 'error');
}

// Load active cart
$cart = null; $items = [];
$c = db()->prepare('SELECT * FROM carts WHERE customer_id=? AND status="active" LIMIT 1');
$c->bind_param('i', $customer_id);
$c->execute();
$cart = $c->get_result()->fetch_assoc();
$c->close();

if ($cart) {
  $cid = (int)$cart['cart_id'];
  $q = db()->prepare('SELECT ci.*, r.title AS room_title, p.title AS property_title
                      FROM cart_items ci
                      LEFT JOIN rooms r ON r.room_id = ci.room_id
                      LEFT JOIN properties p ON p.property_id = ci.property_id
                      WHERE ci.cart_id = ? ORDER BY ci.created_at DESC');
  $q->bind_param('i', $cid);
  $q->execute();
  $res = $q->get_result();
  while ($row = $res->fetch_assoc()) { $items[] = $row; }
  $q->close();
}

function fmt($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Cart</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">My Cart</h1>
    <a href="<?= $base_url ?>/index.php" class="btn btn-outline-secondary btn-sm">Continue Browsing</a>
  </div>

  <div class="card mb-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Item</th>
              <th>Type</th>
              <th>Period</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php $grand = 0.0; foreach ($items as $it): $grand += (float)$it['total_price']; ?>
              <tr>
                <td><?php echo (int)$it['item_id']; ?></td>
                <td><?php echo htmlspecialchars($it['room_title'] ?: $it['property_title'] ?: ''); ?></td>
                <td><?php echo htmlspecialchars($it['item_type']); ?></td>
                <td><?php echo htmlspecialchars(($it['start_date'] ?? '') . ' → ' . ($it['end_date'] ?? '')); ?></td>
                <td><?php echo (int)($it['quantity'] ?? 1); ?></td>
                <td><?php echo fmt($it['price']); ?></td>
                <td><?php echo fmt($it['total_price']); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
              <tr><td colspan="7" class="text-center py-4">Your cart is empty.</td></tr>
            <?php endif; ?>
          </tbody>
          <?php if ($items): ?>
          <tfoot>
            <tr>
              <th colspan="6" class="text-end">Grand Total</th>
              <th><?php echo fmt($grand); ?></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

  <?php if ($items): ?>
    <div class="d-flex justify-content-end">
      <a class="btn btn-success" href="<?= $base_url ?>/public/includes/checkout.php">Proceed to Checkout</a>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
