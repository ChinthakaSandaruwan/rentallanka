<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = ___DIR___ . '/../../error/error.log';
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

require_once ___DIR___ . '/../../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
$okmsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Invalid request.', 'error');
    exit;
  } else {
    $action = $_POST['action'] ?? '';
    $rid = (int)($_POST['room_id'] ?? 0);
    if ($rid <= 0) {
      redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Bad input.', 'error');
      exit;
    } else if ($action === 'approve') {
      // Transition to available and notify owner
      $owner_id = 0; $cur_status = '';
      $g = db()->prepare('SELECT status, owner_id FROM rooms WHERE room_id=?');
      $g->bind_param('i', $rid);
      $g->execute();
      $r = $g->get_result()->fetch_assoc();
      $g->close();
      if ($r) { $cur_status = (string)$r['status']; $owner_id = (int)$r['owner_id']; }

      if ($cur_status === 'available') {
        redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Room is already available.', 'success');
        exit;
      } else {
        $st = db()->prepare('UPDATE rooms SET status = ? WHERE room_id = ?');
        $avail = 'available';
        $st->bind_param('si', $avail, $rid);
        if ($st->execute()) {
          $st->close();
          // Notify owner best-effort
          if ($owner_id > 0) {
            try {
              // notifications table does not have room_id; use property_id=NULL
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $title = 'Room approved';
              $msg = 'Your room #' . $rid . ' was approved and is now available.';
              $type = 'system';
              $nt->bind_param('isss', $owner_id, $title, $msg, $type);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore notification failure */ }
          }
          redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Room approved.', 'success');
          exit;
        } else { $st->close(); redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Approval failed.', 'error'); exit; }
      }
    } else if ($action === 'reject') {
      // Set to unavailable and notify owner
      $owner_id = 0;
      $g = db()->prepare('SELECT owner_id FROM rooms WHERE room_id=?');
      $g->bind_param('i', $rid);
      $g->execute();
      $row = $g->get_result()->fetch_assoc();
      $g->close();
      if ($row) { $owner_id = (int)$row['owner_id']; }

      $st = db()->prepare('UPDATE rooms SET status = ? WHERE room_id = ?');
      $new_status = 'unavailable';
      $st->bind_param('si', $new_status, $rid);
      if ($st->execute()) {
        // Restore one room slot to owner's active paid package (since it was deducted on creation)
        if ($owner_id > 0) {
          try {
            $q = db()->prepare("SELECT bp.bought_package_id\n                                 FROM bought_packages bp\n                                 JOIN packages p ON p.package_id = bp.package_id\n                                 WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'\n                                   AND (bp.end_date IS NULL OR bp.end_date>=NOW())\n                                   AND COALESCE(p.max_rooms,0) > 0\n                                 ORDER BY bp.start_date DESC LIMIT 1");
            $q->bind_param('i', $owner_id);
            $q->execute();
            $bp = $q->get_result()->fetch_assoc();
            $q->close();
            if ($bp && !empty($bp['bought_package_id'])) {
              $bp_id = (int)$bp['bought_package_id'];
              $inc = db()->prepare('UPDATE bought_packages SET remaining_rooms = COALESCE(remaining_rooms,0) + 1 WHERE bought_package_id = ?');
              $inc->bind_param('i', $bp_id);
              $inc->execute();
              $inc->close();
            }
          } catch (Throwable $e) { /* ignore package restore failure */ }
        }
        if ($owner_id > 0) {
          try {
            $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
            $title = 'Room rejected';
            $msg = 'Your room #' . $rid . ' was rejected by admin.';
            $type = 'system';
            $nt->bind_param('isss', $owner_id, $title, $msg, $type);
            $nt->execute();
            $nt->close();
          } catch (Throwable $e) { /* ignore notification failure */ }
        }
        redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Room rejected.', 'success');
        exit;
      } else { $st->close(); redirect_with_message(($GLOBALS['base_url'] ?? '') . '/admin/room/room_approval.php', 'Reject failed.', 'error'); exit; }
    }
  }
}

// Fetch pending rooms
$rows = [];
$sql = 'SELECT r.*, u.name AS owner_name, u.user_id AS owner_id
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id
        WHERE r.status = \'pending\' ORDER BY r.created_at DESC, r.room_id DESC';
$res = db()->query($sql);
while ($res && ($r = $res->fetch_assoc())) { $rows[] = $r; }

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Room Approval</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once ___DIR___ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0">Room Approval</h1>
      <div class="d-flex gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>
    <?php /* Flash and inline messages handled by SweetAlert2 via navbar; remove Bootstrap alerts */ ?>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:80px;">ID</th>
                <th>Title</th>
                <th>Owner</th>
                <th class="text-end">Price/day</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $p): ?>
                <tr>
                  <td>#<?php echo (int)$p['room_id']; ?></td>
                  <td><?php echo htmlspecialchars($p['title'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars(($p['owner_name'] ?? 'N/A') . ' (#' . (int)($p['owner_id'] ?? 0) . ')'); ?></td>
                  <td class="text-end">LKR <?php echo number_format((float)($p['price_per_day'] ?? 0), 2); ?></td>
                  <td><?php echo htmlspecialchars($p['created_at'] ?? ''); ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="room_view.php?id=<?php echo (int)$p['room_id']; ?>">View</a>
                    <form method="post" class="d-inline ra-approve-form">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="room_id" value="<?php echo (int)$p['room_id']; ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                    <form method="post" class="d-inline ra-reject-form">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="room_id" value="<?php echo (int)$p['room_id']; ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center py-4">No pending rooms.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script>
    (function(){
      try {
        // Approve confirmation
        document.querySelectorAll('form.ra-approve-form').forEach(function(form){
          form.addEventListener('submit', async function(e){
            e.preventDefault();
            const res = await Swal.fire({
              title: 'Approve this room?',
              text: 'It will be set to available.',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, approve',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          });
        });
        // Reject confirmation
        document.querySelectorAll('form.ra-reject-form').forEach(function(form){
          form.addEventListener('submit', async function(e){
            e.preventDefault();
            const res = await Swal.fire({
              title: 'Reject this room?',
              text: 'It will be set to unavailable and the owner will be notified.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Yes, reject',
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

