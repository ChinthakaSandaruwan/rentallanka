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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       MY RENTALS PAGE CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
    }
    
    /* Page Header */
    .rl-page-header {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      padding: 1.5rem;
      box-shadow: var(--rl-shadow-sm);
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 1.875rem);
      font-weight: 800;
      color: var(--rl-text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .rl-page-title i {
      color: var(--rl-accent);
      font-size: 1.5rem;
    }
    
    /* Section Headers */
    .rl-section-header {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--rl-text);
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-section-header i {
      color: var(--rl-dark);
    }
    
    /* Table Container */
    .rl-table-container {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-sm);
      overflow: hidden;
      margin-bottom: 2rem;
    }
    
    /* Table Styling */
    .rl-table {
      margin-bottom: 0;
    }
    
    .rl-table thead th {
      background: linear-gradient(135deg, #f8fafc 0%, var(--rl-white) 100%);
      border-bottom: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
      font-weight: 700;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 1rem 0.75rem;
      white-space: nowrap;
    }
    
    .rl-table tbody tr {
      border-bottom: 1px solid var(--rl-border);
      transition: background-color 0.2s ease;
    }
    
    .rl-table tbody tr:hover {
      background: rgba(0, 78, 152, 0.02);
    }
    
    .rl-table tbody tr:last-child {
      border-bottom: none;
    }
    
    .rl-table tbody td {
      padding: 1rem 0.75rem;
      vertical-align: middle;
      font-size: 0.9375rem;
    }
    
    .rl-table td:first-child {
      font-weight: 600;
      color: var(--rl-accent);
    }
    
    /* Price Styling */
    .rl-price {
      font-weight: 700;
      color: var(--rl-dark);
    }
    
    /* Status Badges */
    .rl-badge {
      border-radius: 8px;
      padding: 0.375rem 0.75rem;
      font-weight: 700;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      display: inline-block;
    }
    
    .rl-badge-pending {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      border: 1px solid #fbbf24;
    }
    
    .rl-badge-booked,
    .rl-badge-primary {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      color: #1e3a8a;
      border: 1px solid #93c5fd;
    }
    
    .rl-badge-checked-in,
    .rl-badge-success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      border: 1px solid #6ee7b7;
    }
    
    .rl-badge-cancelled,
    .rl-badge-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      border: 1px solid #f87171;
    }
    
    .rl-badge-checked-out,
    .rl-badge-secondary {
      background: linear-gradient(135deg, var(--rl-light-bg) 0%, #d1d1d1 100%);
      color: var(--rl-text-secondary);
      border: 1px solid var(--rl-secondary);
    }
    
    /* Buttons */
    .rl-btn {
      border-radius: 8px;
      font-weight: 600;
      padding: 0.5rem 1rem;
      transition: all 0.2s ease;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
    }
    
    .rl-btn-outline {
      background: var(--rl-white);
      border: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
    }
    
    .rl-btn-outline:hover {
      background: rgba(0, 78, 152, 0.05);
      border-color: var(--rl-accent);
      color: var(--rl-accent);
      transform: translateY(-1px);
    }
    
    .rl-btn-danger {
      background: var(--rl-white);
      border: 2px solid #ef4444;
      color: #ef4444;
    }
    
    .rl-btn-danger:hover {
      background: #ef4444;
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    .rl-btn-home {
      background: var(--rl-white);
      border: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
    }
    
    .rl-btn-home:hover {
      background: var(--rl-primary);
      border-color: var(--rl-primary);
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    /* Empty State */
    .rl-empty-state {
      text-align: center;
      padding: 3rem 2rem;
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-sm);
      color: var(--rl-text-muted);
      font-weight: 500;
    }
    
    .rl-empty-state i {
      font-size: 3rem;
      color: var(--rl-secondary);
      margin-bottom: 1rem;
    }
    
    /* Divider */
    .rl-divider {
      border: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent 0%, var(--rl-border) 50%, transparent 100%);
      margin: 3rem 0;
    }
    
    /* Responsive Table */
    @media (max-width: 991px) {
      .rl-table-container {
        overflow-x: auto;
      }
      
      .rl-table {
        min-width: 800px;
      }
    }
    
    @media (max-width: 767px) {
      .rl-container {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
      }
      
      .rl-page-header {
        padding: 1.25rem;
        flex-direction: column;
        align-items: flex-start;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
      }
      
      .rl-btn-home {
        width: 100%;
        justify-content: center;
      }
      
      .rl-table thead th,
      .rl-table tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.8125rem;
      }
    }
    
    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .rl-page-header,
    .rl-table-container,
    .rl-empty-state {
      animation: fadeInUp 0.5s ease-out;
    }
    
    .rl-table-container:nth-of-type(2) {
      animation-delay: 0.1s;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container rl-container">
  <!-- Page Header -->
  <div class="rl-page-header">
    <h1 class="rl-page-title">
      <i class="bi bi-building"></i>
      My Rentals
    </h1>
    <a href="<?php echo $base_url; ?>/" class="rl-btn rl-btn-home">
      <i class="bi bi-house"></i>
      Back to Home
    </a>
  </div>

  <!-- Room Rentals Section -->
  <?php if (!$items): ?>
    <div class="rl-empty-state">
      <i class="bi bi-inbox"></i>
      <p class="mb-0">You have no rentals yet.</p>
    </div>
  <?php else: ?>
    <h3 class="rl-section-header">
      <i class="bi bi-door-open"></i>
      Room Rentals
    </h3>
    <div class="rl-table-container">
      <div class="table-responsive">
        <table class="table rl-table">
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
                <td class="rl-price">LKR <?php echo number_format((float)$it['price_per_night'], 2); ?></td>
                <td class="rl-price">LKR <?php echo number_format((float)$it['total_amount'], 2); ?></td>
                <td>
                  <?php $st = (string)($it['status'] ?? ''); $cls = ['pending'=>'pending','booked'=>'booked','checked_in'=>'checked-in','checked_out'=>'checked-out','cancelled'=>'cancelled'][$st] ?? 'secondary'; ?>
                  <span class="rl-badge rl-badge-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st ?: 'booked'))); ?></span>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$it['room_id']; ?>" class="rl-btn rl-btn-outline">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <?php $nowTs = time(); $ciTs = strtotime((string)$it['checkin_date']); $coTs = strtotime((string)$it['checkout_date']); $st = (string)$it['status']; ?>
                    <?php if (in_array($st, ['pending','booked'], true)): ?>
                      <form method="post" class="d-inline rent-cancel-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="rl-btn rl-btn-danger">
                          <i class="bi bi-x-circle"></i> Cancel
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  
  <!-- Section Divider -->
  <hr class="rl-divider">
  
  <!-- Property Rent Requests Section -->
  <?php if (!$props): ?>
    <div class="rl-empty-state">
      <i class="bi bi-inbox"></i>
      <p class="mb-0">You have no property rent requests yet.</p>
    </div>
  <?php else: ?>
    <h3 class="rl-section-header">
      <i class="bi bi-house-door"></i>
      My Property Rent Requests
    </h3>
    <div class="rl-table-container">
      <div class="table-responsive">
        <table class="table rl-table">
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
                <td class="rl-price">LKR <?php echo number_format((float)($p['price_per_month'] ?? 0), 2); ?></td>
                <td>
                  <?php $stp = (string)($p['status'] ?? ''); $cls = ['pending'=>'pending','booked'=>'booked','cancelled'=>'cancelled'][$stp] ?? 'secondary'; ?>
                  <span class="rl-badge rl-badge-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $stp ?: 'pending'))); ?></span>
                </td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($p['created_at'] ?? '')))); ?></td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>" class="rl-btn rl-btn-outline">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <?php if (in_array(strtolower((string)$p['status']), ['pending','booked'], true)): ?>
                    <form method="post" class="d-inline prop-cancel-form">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="rent_id" value="<?php echo (int)$p['rent_id']; ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="scope" value="property">
                      <button type="submit" class="rl-btn rl-btn-danger">
                        <i class="bi bi-x-circle"></i> Cancel
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
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

