<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/auth_guard.php'; require_role('customer');

$customer_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($customer_id <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please login', 'error');
}

// Load active cart and items
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
                      WHERE ci.cart_id = ? ORDER BY ci.created_at ASC');
  $q->bind_param('i', $cid);
  $q->execute();
  $res = $q->get_result();
  while ($row = $res->fetch_assoc()) { $items[] = $row; }
  $q->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$cart || !$items) {
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/cart.php', 'No items to checkout', 'error');
  }
  $cid = (int)$cart['cart_id'];
  db()->begin_transaction();
  try {
    foreach ($items as $it) {
      $type = $it['item_type'] ?? '';
      $start = $it['start_date'] ?? null;
      $end = $it['end_date'] ?? null;
      $total = (float)($it['total_price'] ?? 0);
      if ($type === 'monthly_property' && !empty($it['property_id'])) {
        // Create rental
        $ins = db()->prepare('INSERT INTO rentals (property_id, customer_id, start_date, end_date, total_price, status) VALUES (?, ?, ?, ?, ?, "active")');
        $ins->bind_param('iissd', $it['property_id'], $customer_id, $start, $end, $total);
        $ins->execute();
        $ins->close();
        // Mark property rented
        $up = db()->prepare('UPDATE properties SET status="rented" WHERE property_id=?');
        $up->bind_param('i', $it['property_id']);
        $up->execute();
        $up->close();
      } elseif ($type === 'daily_room' && !empty($it['room_id'])) {
        // Create room rental
        $insr = db()->prepare('INSERT INTO room_rentals (room_id, customer_id, start_date, end_date, total_price, status) VALUES (?, ?, ?, ?, ?, "active")');
        $insr->bind_param('iissd', $it['room_id'], $customer_id, $start, $end, $total);
        $insr->execute();
        $booking_id = (int)db()->insert_id;
        $insr->close();
        // Mark room rented
        $upr = db()->prepare('UPDATE rooms SET status="rented" WHERE room_id=?');
        $upr->bind_param('i', $it['room_id']);
        $upr->execute();
        $upr->close();
      }
    }
    // Close cart
    $cc = db()->prepare('UPDATE carts SET status="checked_out" WHERE cart_id=?');
    $cc->bind_param('i', $cid);
    $cc->execute();
    $cc->close();

    db()->commit();
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/cart.php', 'Checkout complete', 'success');
  } catch (Throwable $e) {
    db()->rollback();
    redirect_with_message($GLOBALS['base_url'] . '/public/includes/cart.php', 'Checkout failed', 'error');
  }
}

function fmt($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Checkout</h1>
    <a href="<?= $base_url ?>/public/includes/cart.php" class="btn btn-outline-secondary btn-sm">Back to Cart</a>
  </div>
  <?php if (!$cart || !$items): ?>
    <div class="alert alert-warning">No items to checkout.</div>
  <?php else: ?>
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
                  <td><?= (int)$it['item_id'] ?></td>
                  <td><?= htmlspecialchars($it['room_title'] ?: $it['property_title'] ?: '') ?></td>
                  <td><?= htmlspecialchars($it['item_type']) ?></td>
                  <td><?= htmlspecialchars(($it['start_date'] ?? '') . ' → ' . ($it['end_date'] ?? '')) ?></td>
                  <td><?= (int)($it['quantity'] ?? 1) ?></td>
                  <td><?= fmt($it['price']) ?></td>
                  <td><?= fmt($it['total_price']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="6" class="text-end">Grand Total</th>
                <th><?= fmt($grand) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
    <form method="post">
      <button type="submit" class="btn btn-success">Confirm Checkout</button>
    </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
