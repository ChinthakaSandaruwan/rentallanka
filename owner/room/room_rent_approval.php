<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');

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
              $msgC = 'Your booking #' . (int)$rent_id . ' for room ' . ($roomTitle !== '' ? $roomTitle : '') . ' is confirmed from ' . $ciLbl . ' to ' . $coLbl . '.';
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
  <title>Room Rent Approvals</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-check me-2"></i>Room Rent Approvals</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
  </div>

  <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
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
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <button class="btn btn-sm btn-success" name="action" value="approve"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                  </form>
                  <form method="post" class="d-inline ms-1" onsubmit="return confirm('Decline this booking?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <button class="btn btn-sm btn-outline-danger" name="action" value="decline"><i class="bi bi-x-circle me-1"></i>Decline</button>
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
</body>
</html>

