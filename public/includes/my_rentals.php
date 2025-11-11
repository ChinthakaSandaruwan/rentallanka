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
  $scope = (string)($_POST['scope'] ?? 'room');
  if (!hash_equals($csrf, $token)) {
    redirect_with_message($base_url . '/public/includes/my_rentals.php', 'Invalid request.', 'error');
  }
  if ($scope === 'property' && $rent_id > 0 && $action === 'cancel') {
    // Property rent cancel
    // Load property rent for this user
    $st = db()->prepare('SELECT rent_id, property_id, status, created_at FROM property_rents WHERE rent_id=? AND customer_id=? LIMIT 1');
    $st->bind_param('ii', $rent_id, $uid);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    $ok = false;
    if ($r) {
      $cur = strtolower((string)($r['status'] ?? ''));
      $property_id = (int)($r['property_id'] ?? 0);
      if (in_array($cur, ['pending','booked'], true)) {
        if (function_exists('app_log')) { app_log('[my_rentals] property_cancel attempt uid='.(int)$uid.' rent_id='.(int)$rent_id.' cur_status='.$cur); }
        $up = db()->prepare("UPDATE property_rents SET status='cancelled' WHERE rent_id=? AND LOWER(status) IN ('pending','booked')");
        $up->bind_param('i', $rent_id);
        $ok = $up->execute();
        if (function_exists('app_log')) { app_log('[my_rentals] property_cancel update affected='.(int)$up->affected_rows.' ok='.(int)$ok); }
        $up->close();
        if ($ok) {
          if (function_exists('app_log')) { app_log('[my_rentals] property_cancel success uid='.(int)$uid.' rent_id='.(int)$rent_id); }
          // Notifications
          try {
            $propTitle = '';
            $ow = 0;
            $gr = db()->prepare('SELECT owner_id, title FROM properties WHERE property_id=? LIMIT 1');
            $gr->bind_param('i', $property_id);
            $gr->execute();
            $rr = $gr->get_result()->fetch_assoc();
            $gr->close();
            if ($rr) { $ow = (int)$rr['owner_id']; $propTitle = (string)($rr['title'] ?? ''); }
            if ($ow > 0) {
              $titleN = 'Property rent cancelled';
              $msgN = 'Customer #' . (int)$uid . ' cancelled rent request #' . (int)$rent_id . ' for property ' . ($propTitle !== '' ? $propTitle : ('#'.$property_id)) . '.';
              $typeN = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $pidN = $property_id;
              $nt->bind_param('isssi', $ow, $titleN, $msgN, $typeN, $pidN);
              $nt->execute();
              $nt->close();
            }
            // Customer confirmation
            try {
              $titleC = 'Property rent cancelled';
              $msgC = 'Your rent request #' . (int)$rent_id . ' for property ' . ($propTitle !== '' ? $propTitle : ('#'.$property_id)) . ' has been cancelled.';
              $typeC = 'system';
              $nc = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $pidC = $property_id;
              $nc->bind_param('isssi', $uid, $titleC, $msgC, $typeC, $pidC);
              $nc->execute();
              $nc->close();
            } catch (Throwable $eCc) { /* ignore */ }
          } catch (Throwable $eN) { /* ignore notification failure */ }
        }
      }
    }
    $msg = $ok ? 'Cancelled.' : 'Action not allowed.';
    $typ = $ok ? 'success' : 'error';
    redirect_with_message($base_url . '/public/includes/my_rentals.php', $msg, $typ);
  }
  if ($rent_id > 0 && in_array($action, ['cancel','checkin','checkout'], true)) {
    // Load rental for this user (rooms)
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
      if ($action === 'cancel' && $cur === 'pending') {
        $up2 = db()->prepare("UPDATE room_rents SET status='cancelled' WHERE rent_id=? AND status='pending'");
        $up2->bind_param('i', $rent_id);
        $ok = $up2->execute();
        $up2->close();
        if ($ok) {
          // Free room if no other active bookings
          $q = db()->prepare("SELECT COUNT(*) AS c FROM room_rents WHERE room_id=? AND status IN ('booked','pending')");
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

          // Notify the room owner (best-effort)
          try {
            $ow = 0; $roomTitle = '';
            $gr = db()->prepare('SELECT owner_id, title FROM rooms WHERE room_id=? LIMIT 1');
            $gr->bind_param('i', $room_id);
            $gr->execute();
            $rr = $gr->get_result()->fetch_assoc();
            $gr->close();
            if ($rr) { $ow = (int)$rr['owner_id']; $roomTitle = (string)($rr['title'] ?? ''); }
            if ($ow > 0) {
              $titleN = 'Booking cancelled';
              $ciLbl = $ci->format('Y-m-d');
              $coLbl = $co->format('Y-m-d');
              $msgN = 'Customer #' . (int)$uid . ' cancelled booking #' . (int)$rent_id . ' for room ' . ($roomTitle !== '' ? $roomTitle : ('#'.$room_id)) . ' (' . $ciLbl . ' to ' . $coLbl . ').';
              $typeN = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $nt->bind_param('isss', $ow, $titleN, $msgN, $typeN);
              $nt->execute();
              $nt->close();
            }

            // Notify the customer (confirmation)
            try {
              $titleC = 'Booking cancelled';
              $msgC = 'Your booking #' . (int)$rent_id . ' for room ' . ($roomTitle !== '' ? $roomTitle : ('#'.$room_id)) . ' (' . $ciLbl . ' to ' . $coLbl . ') has been cancelled.';
              $typeC = 'system';
              $nc = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $nc->bind_param('isss', $uid, $titleC, $msgC, $typeC);
              $nc->execute();
              $nc->close();
            } catch (Throwable $eCc) { /* ignore */ }
          } catch (Throwable $eN) { /* ignore notification failure */ }
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
      $msg = $ok ? 'Cancelled.' : 'Action not allowed.';
      $typ = $ok ? 'success' : 'error';
      redirect_with_message($base_url . '/public/includes/my_rentals.php', $msg, $typ);
    }
  }
}

// Fetch user's rentals
$items = [];
$sql = 'SELECT rr.rent_id, rr.room_id, rr.checkin_date, rr.checkout_date, rr.guests, rr.price_per_night, rr.total_amount, rr.status,
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

// Fetch user's property rent requests
$props = [];
try {
  $ps = db()->prepare('SELECT pr.rent_id, pr.property_id, pr.price_per_month, pr.status, pr.created_at, p.title AS property_title
                       FROM property_rents pr
                       LEFT JOIN properties p ON p.property_id = pr.property_id
                       WHERE pr.customer_id = ?
                       ORDER BY pr.rent_id DESC');
  $ps->bind_param('i', $uid);
  $ps->execute();
  $pr = $ps->get_result();
  while ($row = $pr->fetch_assoc()) { $props[] = $row; }
  $ps->close();
} catch (Throwable $e) { $props = []; }
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
    <p class="text-muted">You have no rentals yet.</p>
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
            <th>Price/Night</th>
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
              <td>LKR <?php echo number_format((float)$it['price_per_night'], 2); ?></td>
              <td class="fw-semibold">LKR <?php echo number_format((float)$it['total_amount'], 2); ?></td>
              <td>
                <?php $st = (string)($it['status'] ?? ''); $cls = ['pending'=>'warning','booked'=>'primary','checked_in'=>'success','checked_out'=>'secondary','cancelled'=>'danger'][$st] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st ?: 'booked'))); ?></span>
              </td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$it['room_id']; ?>" class="btn btn-outline-secondary btn-sm">View</a>
                  <?php $nowTs = time(); $ciTs = strtotime((string)$it['checkin_date']); $coTs = strtotime((string)$it['checkout_date']); $st = (string)$it['status']; ?>
                  <?php if (in_array($st, ['pending','booked'], true)): ?>
                    <form method="post" class="d-inline rent-cancel-form">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                      <input type="hidden" name="action" value="cancel">
                      <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
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
  <hr class="my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 mb-0"><i class="bi bi-house-door me-2"></i>My Property Rent Requests</h2>
  </div>
  <?php if (!$props): ?>
    <p class="text-muted">You have no property rent requests yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Property</th>
            <th>Price/Month</th>
            <th>Status</th>
            <th>Requested At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($props as $p): ?>
            <tr>
              <td>#<?php echo (int)$p['rent_id']; ?></td>
              <td><?php echo htmlspecialchars($p['property_title'] ?? ('Property #' . (int)$p['property_id'])); ?></td>
              <td>LKR <?php echo number_format((float)($p['price_per_month'] ?? 0), 2); ?></td>
              <td>
                <?php $stp = (string)($p['status'] ?? ''); $cls = ['pending'=>'warning','booked'=>'primary','cancelled'=>'secondary'][$stp] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $stp ?: 'pending'))); ?></span>
              </td>
              <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($p['created_at'] ?? '')))); ?></td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>" class="btn btn-outline-secondary btn-sm">View</a>
                  <?php if (in_array(strtolower((string)$p['status']), ['pending','booked'], true)): ?>
                  <form method="post" class="d-inline prop-cancel-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$p['rent_id']; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="scope" value="property">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      // Room booking cancellation
      document.querySelectorAll('form.rent-cancel-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Cancel booking?',
            text: 'Cancel booking #' + rid + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel',
            cancelButtonText: 'Keep'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
      // Property request cancellation
      document.querySelectorAll('form.prop-cancel-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Cancel request?',
            text: 'Cancel request #' + rid + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel',
            cancelButtonText: 'Keep'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
</body>
</html>

