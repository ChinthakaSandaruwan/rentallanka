<?php
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  redirect_with_message(rtrim($base_url,'/') . '/auth/login.php', 'Please sign in to view your rentals', 'info');
}

// CSRF token for actions
if (empty($_SESSION['csrf_rentals'])) {
  $_SESSION['csrf_rentals'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_rentals'];

// Handle actions: cancel, checkin, checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  $action = $_POST['action'] ?? '';
  $rent_id = (int)($_POST['rent_id'] ?? 0);
  if (!hash_equals($csrf, $token)) {
    redirect_with_message($base_url . '/public/includes/my_rentals.php', 'Invalid request.', 'error');
  }
  if ($rent_id > 0 && in_array($action, ['cancel','checkin','checkout'], true)) {
    // Load rental for this user
    $st = db()->prepare('SELECT rent_id, room_id, status, checkin_date, checkout_date FROM room_rents WHERE rent_id=? AND customer_id=? LIMIT 1');
    $st->bind_param('ii', $rent_id, $uid);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    if ($r) {
      $now = new DateTime('now');
      $ci = new DateTime((string)$r['checkin_date']);
      $co = new DateTime((string)$r['checkout_date']);
      $cur = (string)$r['status'];
      $room_id = (int)$r['room_id'];
      $ok = false;
      if ($action === 'cancel' && $cur === 'booked' && $now < $ci) {
        $up = db()->prepare("UPDATE room_rents SET status='cancelled' WHERE rent_id=? AND status='booked'");
        $up->bind_param('i', $rent_id);
        $ok = $up->execute();
        $up->close();
        if ($ok) {
          // Free room if no other active bookings
          $q = db()->prepare("SELECT COUNT(*) AS c FROM room_rents WHERE room_id=? AND status IN ('booked','checked_in')");
          $q->bind_param('i', $room_id);
          $q->execute();
          $cnt = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
          $q->close();
          if ($cnt === 0) {
            $fr = db()->prepare("UPDATE rooms SET status='available' WHERE room_id=?");
            $fr->bind_param('i', $room_id);
            $fr->execute();
            $fr->close();
          }
        }
      } elseif ($action === 'checkin' && $cur === 'booked' && $now >= $ci && $now < $co) {
        $up = db()->prepare("UPDATE room_rents SET status='checked_in' WHERE rent_id=? AND status='booked'");
        $up->bind_param('i', $rent_id);
        $ok = $up->execute();
        $up->close();
        // Room already set as rented on booking
      } elseif ($action === 'checkout' && $cur === 'checked_in') {
        $up = db()->prepare("UPDATE room_rents SET status='checked_out' WHERE rent_id=? AND status='checked_in'");
        $up->bind_param('i', $rent_id);
        $ok = $up->execute();
        $up->close();
        if ($ok) {
          // Free room if no other active bookings
          $q = db()->prepare("SELECT COUNT(*) AS c FROM room_rents WHERE room_id=? AND status IN ('booked','checked_in')");
          $q->bind_param('i', $room_id);
          $q->execute();
          $cnt = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
          $q->close();
          if ($cnt === 0) {
            $fr = db()->prepare("UPDATE rooms SET status='available' WHERE room_id=?");
            $fr->bind_param('i', $room_id);
            $fr->execute();
            $fr->close();
          }
        }
      }
      $msg = $ok ? 'Action completed.' : 'Action not allowed.';
      $typ = $ok ? 'success' : 'error';
      redirect_with_message($base_url . '/public/includes/my_rentals.php', $msg, $typ);
    }
  }
}

// Fetch user's rentals
$items = [];
$sql = 'SELECT rr.rent_id, rr.room_id, rr.checkin_date, rr.checkout_date, rr.guests, rr.price_per_day, rr.total_amount, rr.status,
               r.title AS room_title,
               rm.meal_name,
               rm.price AS meal_price
        FROM room_rents rr
        LEFT JOIN rooms r ON r.room_id = rr.room_id
        LEFT JOIN room_meals rm ON rm.room_id = rr.room_id AND rm.meal_id = rr.meal_id
        WHERE rr.customer_id = ?
        ORDER BY rr.rent_id DESC';
$st = db()->prepare($sql);
$st->bind_param('i', $uid);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$st->close();

function fmt_date($d) { return $d ? date('Y-m-d', strtotime((string)$d)) : ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Rentals</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-receipt me-2"></i>My Rentals</h1>
    <a href="<?php echo $base_url; ?>/" class="btn btn-outline-secondary btn-sm">Home</a>
  </div>

  <?php if (!$items): ?>
    <div class="alert alert-light border">You have no rentals yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Room</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Guests</th>
            <th>Meal</th>
            <th>Price/Day</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>#<?php echo (int)$it['rent_id']; ?></td>
              <td><?php echo htmlspecialchars($it['room_title'] ?? ('Room #' . (int)$it['room_id'])); ?></td>
              <td><?php echo htmlspecialchars(fmt_date($it['checkin_date'])); ?></td>
              <td><?php echo htmlspecialchars(fmt_date($it['checkout_date'])); ?></td>
              <td><?php echo (int)$it['guests']; ?></td>
              <td><?php echo htmlspecialchars($it['meal_name'] ? ucwords(str_replace('_',' ', $it['meal_name'])) : 'No meals'); ?></td>
              <td>LKR <?php echo number_format((float)$it['price_per_day'], 2); ?></td>
              <td class="fw-semibold">LKR <?php echo number_format((float)$it['total_amount'], 2); ?></td>
              <td>
                <?php $st = (string)($it['status'] ?? ''); $cls = ['booked'=>'primary','checked_in'=>'success','checked_out'=>'secondary','cancelled'=>'danger'][$st] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st ?: 'booked'))); ?></span>
              </td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$it['room_id']; ?>" class="btn btn-outline-secondary btn-sm">View</a>
                  <?php $nowTs = time(); $ciTs = strtotime((string)$it['checkin_date']); $coTs = strtotime((string)$it['checkout_date']); $st = (string)$it['status']; ?>
                  <?php if ($st === 'booked' && $nowTs < $ciTs): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                      <input type="hidden" name="action" value="cancel">
                      <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this booking?');">Cancel</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st === 'booked' && $nowTs >= $ciTs && $nowTs < $coTs): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                      <input type="hidden" name="action" value="checkin">
                      <button type="submit" class="btn btn-outline-primary btn-sm">Check-in</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st === 'checked_in'): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                      <input type="hidden" name="action" value="checkout">
                      <button type="submit" class="btn btn-outline-success btn-sm">Check-out</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

