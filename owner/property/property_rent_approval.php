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

// CSRF
if (empty($_SESSION['csrf_prop_approvals'])) {
  $_SESSION['csrf_prop_approvals'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_prop_approvals'];

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
$err = '';
$ok = '';

// Handle actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $token)) {
    $err = 'Invalid request.';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $rent_id = (int)($_POST['rent_id'] ?? 0);
    if ($rent_id <= 0) {
      $err = 'Bad input.';
    } else {
      // Verify that this rent belongs to a property owned by this owner
      $q = db()->prepare("SELECT pr.rent_id, pr.property_id, pr.customer_id, pr.status, p.title AS property_title
                          FROM property_rents pr JOIN properties p ON p.property_id = pr.property_id
                          WHERE pr.rent_id = ? AND p.owner_id = ? LIMIT 1");
      $q->bind_param('ii', $rent_id, $owner_id);
      $q->execute();
      $row = $q->get_result()->fetch_assoc();
      $q->close();
      if (!$row) {
        $err = 'Rent request not found.';
      } else if ((string)$row['status'] !== 'pending') {
        $err = 'Only pending requests can be processed.';
      } else {
        $cid = (int)$row['customer_id'];
        $ptitle = (string)($row['property_title'] ?? '');
        if ($action === 'approve') {
          $st = db()->prepare("UPDATE property_rents SET status='booked' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Rent request approved.';
            // Notify customer
            try {
              $titleC = 'Congratulations Property Rent Confirmed';
              $msgC = 'Your rent request #' . (int)$rent_id . ' for property ' . ($ptitle !== '' ? $ptitle : '') . ' is confirmed.';
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $propIdNullable = (int)$row['property_id'];
              $nt->bind_param('isssi', $cid, $titleC, $msgC, $typeC, $propIdNullable);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore */ }
          } else {
            $err = 'Approval failed.';
          }
          $st->close();
        } else if ($action === 'decline') {
          $st = db()->prepare("UPDATE property_rents SET status='cancelled' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Rent request declined.';
            // Notify customer
            try {
              $titleC = 'We Are Sorry Property Rent Declined';
              $msgC = 'Your rent request #' . (int)$rent_id . ' for property ' . ($ptitle !== '' ? $ptitle : '') . ' was declined by the owner.';
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $propIdNullable = (int)$row['property_id'];
              $nt->bind_param('isssi', $cid, $titleC, $msgC, $typeC, $propIdNullable);
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
  // POST-Redirect-GET: always redirect after POST to avoid resubmission on refresh
  $msg = $ok ?: $err ?: 'Action completed.';
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/owner/property/property_rent_approval.php', $msg, $typ);
  exit;
}

// List pending property rents for this owner's properties
$rows = [];
$sql = "SELECT pr.rent_id, pr.property_id, pr.customer_id, pr.price_per_month, pr.status, pr.created_at,
               u.name AS customer_name,
               p.title AS property_title
        FROM property_rents pr
        JOIN properties p ON p.property_id = pr.property_id
        JOIN users u ON u.user_id = pr.customer_id
        WHERE p.owner_id = ? AND pr.status = 'pending'
        ORDER BY pr.created_at DESC, pr.rent_id DESC";
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
  <title>Property Rent Approvals</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-check me-2"></i>Property Rent Approvals</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
  </div>

  <?php /* Alerts handled globally via SweetAlert2 (navbar). Removed Bootstrap alerts. */ ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Property</th>
              <th>Customer</th>
              <th>Price/Month</th>
              <th>Requested At</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $it): ?>
              <tr>
                <td>#<?php echo (int)$it['rent_id']; ?></td>
                <td><?php echo htmlspecialchars($it['property_title'] ?? ('Property #' . (int)$it['property_id'])); ?></td>
                <td><?php echo htmlspecialchars(($it['customer_name'] ?? 'User') . ' (#' . (int)$it['customer_id'] . ')'); ?></td>
                <td>LKR <?php echo number_format((float)($it['price_per_month'] ?? 0), 2); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$it['created_at']))); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline rent-approve-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                  </form>
                  <form method="post" class="d-inline ms-1 rent-decline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="decline">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Decline</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No pending requests found.</td></tr>
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
      document.querySelectorAll('form.rent-approve-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Approve rent request?',
            text: 'Approve request #' + rid + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
      document.querySelectorAll('form.rent-decline-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Decline rent request?',
            text: 'This cannot be undone. Decline request #' + rid + '?',
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

