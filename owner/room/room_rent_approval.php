<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100; $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096); $read = $pos - $step;
        fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
  }
}

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
$err = '';
$ok = '';

// Handle actions: approve or decline a pending booking for this owner's room
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request.';
  } else {
    $action = $_POST['action'] ?? '';
    $rent_id = (int)($_POST['rent_id'] ?? 0);
    if ($rent_id <= 0) {
      $err = 'Bad input.';
    } else {
      // Verify rental belongs to a room owned by this owner and is pending
      $q = db()->prepare("SELECT rr.rent_id, rr.room_id, rr.customer_id, rr.checkin_date, rr.checkout_date, rr.status, r.title AS room_title
                          FROM room_rents rr JOIN rooms r ON r.room_id = rr.room_id
                          WHERE rr.rent_id = ? AND r.owner_id = ? LIMIT 1");
      $q->bind_param('ii', $rent_id, $owner_id);
      $q->execute();
      $row = $q->get_result()->fetch_assoc();
      $q->close();
      if (!$row) {
        $err = 'Rental not found.';
      } else if ((string)$row['status'] !== 'pending') {
        $err = 'Only pending bookings can be processed.';
      } else {
        $cid = (int)$row['customer_id'];
        $roomTitle = (string)($row['room_title'] ?? '');
        $ciLbl = date('Y-m-d', strtotime((string)$row['checkin_date']));
        $coLbl = date('Y-m-d', strtotime((string)$row['checkout_date']));
        if ($action === 'approve') {
          $st = db()->prepare("UPDATE room_rents SET status='booked' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Booking approved.';
            // Notify customer: booking confirmed (requested phrasing)
            try {
              $titleC = 'Congratulations Booking Confirmed';
              // Lookup owner's phone number to include in confirmation
              $ownerPhone = '';
              try {
                $qp = db()->prepare('SELECT phone FROM users WHERE user_id = ? LIMIT 1');
                $qp->bind_param('i', $owner_id);
                $qp->execute();
                $rs = $qp->get_result();
                $rw = $rs ? $rs->fetch_assoc() : null;
                $ownerPhone = (string)($rw['phone'] ?? '');
                $qp->close();
              } catch (Throwable $_) {}
              $msgC = 'Your booking #' . (int)$rent_id . ' for room ' . ($roomTitle !== '' ? $roomTitle : '') . ' is confirmed from ' . $ciLbl . ' to ' . $coLbl . '.' . ($ownerPhone !== '' ? (' Owner mobile: ' . $ownerPhone . '.') : '');
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $nt->bind_param('isss', $cid, $titleC, $msgC, $typeC);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore */ }
          } else {
            $err = 'Approval failed.';
          }
          $st->close();
        } else if ($action === 'decline') {
          $st = db()->prepare("UPDATE room_rents SET status='cancelled' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Booking declined.';
            // Notify customer: booking declined (requested phrasing)
            try {
              $titleC = 'We Are Sorry Booking Declined';
              $msgC = 'Your booking #' . (int)$rent_id . ' for room ' . ($roomTitle !== '' ? $roomTitle : '') . ' (' . $ciLbl . ' to ' . $coLbl . ') was declined by the owner.';
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $nt->bind_param('isss', $cid, $titleC, $msgC, $typeC);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore */ }
          } else {
            $err = 'Decline failed.';
          }
          $st->close();
        } else {
          $err = 'Unknown action.';
        }
      }
    }
  }
}

// POST-Redirect-GET: always redirect after POST so refresh doesn't resubmit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $msg = $ok ?: ($err ?: 'Action completed.');
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/owner/room/room_rent_approval.php', $msg, $typ);
  exit;
}

// List pending rentals for this owner's rooms
$rows = [];
$sql = "SELECT rr.rent_id, rr.room_id, rr.customer_id, rr.checkin_date, rr.checkout_date, rr.guests, rr.total_amount, rr.status,
               u.name AS customer_name,
               r.title AS room_title
        FROM room_rents rr
        JOIN rooms r ON r.room_id = rr.room_id
        JOIN users u ON u.user_id = rr.customer_id
        WHERE r.owner_id = ? AND rr.status = 'pending'
        ORDER BY rr.checkin_date ASC, rr.rent_id DESC";
$st = db()->prepare($sql);
$st->bind_param('i', $owner_id);
$st->execute();
$rs = $st->get_result();
while ($a = $rs->fetch_assoc()) { $rows[] = $a; }
$st->close();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.25rem 1.75rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.5rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1rem 1.25rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    .table thead th { background:#f8fafc; border-bottom:2px solid var(--rl-border); color:var(--rl-text-secondary); font-weight:700; font-size:.875rem; text-transform:uppercase; letter-spacing:.5px; }
    .table tbody tr { border-bottom:1px solid var(--rl-border); }
    .table tbody tr:hover { background:rgba(0,78,152,.02); }

    @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-journal-check"></i> Room Rent Approvals</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php /* Alerts handled globally via SweetAlert2 (navbar); removed Bootstrap alerts */ ?>

  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-list-check"></i> Pending Bookings</h2></div>
    <div class="rl-form-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Room</th>
              <th>Customer</th>
              <th>Check-in</th>
              <th>Check-out</th>
              <th>Guests</th>
              <th>Total</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $it): ?>
              <tr>
                <td>#<?php echo (int)$it['rent_id']; ?></td>
                <td><?php echo htmlspecialchars($it['room_title'] ?? ('Room #' . (int)$it['room_id'])); ?></td>
                <td><?php echo htmlspecialchars(($it['customer_name'] ?? 'User') . ' (#' . (int)$it['customer_id'] . ')'); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)$it['checkin_date']))); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)$it['checkout_date']))); ?></td>
                <td><?php echo (int)$it['guests']; ?></td>
                <td>LKR <?php echo number_format((float)$it['total_amount'], 2); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline room-approve-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                  </form>
                  <form method="post" class="d-inline ms-1 room-decline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="decline">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Decline</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No pending bookings found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.room-approve-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Approve booking?',
            text: 'Approve booking #' + rid + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
      document.querySelectorAll('form.room-decline-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Decline booking?',
            text: 'This cannot be undone. Decline booking #' + rid + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, decline',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
</body>
</html>

