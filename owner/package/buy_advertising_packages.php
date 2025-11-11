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
?>
<?php require_once __DIR__ . '/../../public/includes/auth_guard.php'; require_role('owner'); ?>
<?php require_once __DIR__ . '/../../config/config.php'; ?>
<?php
  $uid = (int)($_SESSION['user']['user_id'] ?? 0);
  $alert = ['type' => '', 'msg' => ''];
  [$flash, $flash_type] = get_flash();
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      try {
          $pid = (int)($_POST['package_id'] ?? 0);
          if (empty($_FILES['payment_slip']['name']) || !is_uploaded_file($_FILES['payment_slip']['tmp_name'])) {
              throw new Exception('Please upload a payment slip (image/PDF).');
          }
          if ($pid <= 0) { throw new Exception('Invalid package'); }

          $pstmt = db()->prepare('SELECT * FROM packages WHERE package_id=? AND status="active" LIMIT 1');
          $pstmt->bind_param('i', $pid);
          $pstmt->execute();
          $pkg = $pstmt->get_result()->fetch_assoc();
          $pstmt->close();
          if (!$pkg) { throw new Exception('Package not found'); }

          $now = (new DateTime('now'));
          $end = null;
          $duration_days = (int)($pkg['duration_days'] ?? 0);
          $ptype = (string)$pkg['package_type'];
          if (in_array($ptype, ['monthly','yearly'], true) && $duration_days > 0) {
              $endDT = clone $now; $endDT->modify('+' . $duration_days . ' days');
              $end = $endDT->format('Y-m-d H:i:s');
          }
          $remProps = (int)($pkg['max_properties'] ?? 0);
          $remRooms = (int)($pkg['max_rooms'] ?? 0);

          $usql = 'INSERT INTO bought_packages (user_id, package_id, start_date, end_date, remaining_properties, remaining_rooms, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, "active", "pending")';
          $ust = db()->prepare($usql);
          $start = $now->format('Y-m-d H:i:s');
          $endParam = $end; // can be null
          $ust->bind_param('iissii', $uid, $pid, $start, $endParam, $remProps, $remRooms);
          $ust->execute();
          $bought_package_id = (int)$ust->insert_id;
          $ust->close();

          $slipDir = dirname(__DIR__) . '/uploads/package_slips';
          if (!is_dir($slipDir)) { @mkdir($slipDir, 0775, true); }
          $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
          $fname = 'pkgslip_' . $bought_package_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
          $dest = $slipDir . '/' . $fname;
          if (!move_uploaded_file($_FILES['payment_slip']['tmp_name'], $dest)) {
              throw new Exception('Failed to save slip');
          }
          $slipUrl = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/package_slips/' . $fname;

          $amount = (string)$pkg['price'];
          $method = 'bank';
          $sql1 = 'INSERT INTO package_payments (bought_package_id, amount, payment_method, payment_reference, payment_status) VALUES (?, ?, ?, ?, "pending")';
          $pp = db()->prepare($sql1);
          $inserted = false;
          if ($pp) {
              $pp->bind_param('idss', $bought_package_id, $amount, $method, $slipUrl);
              if ($pp->execute()) { $inserted = true; }
              $pp->close();
          }
          if (!$inserted) {
              $sql2 = 'INSERT INTO package_payments (user_package_id, amount, payment_method, payment_reference, payment_status) VALUES (?, ?, ?, ?, "pending")';
              $pp2 = db()->prepare($sql2);
              if (!$pp2) { throw new Exception('Payments table not updated; please run SQL migration.'); }
              $pp2->bind_param('idss', $bought_package_id, $amount, $method, $slipUrl);
              if (!$pp2->execute()) {
                  $err = $pp2->error;
                  $pp2->close();
                  throw new Exception('Failed to record payment: ' . $err);
              }
              $pp2->close();
          }

          redirect_with_message($GLOBALS['base_url'] . '/owner/package/bought_advertising_packages.php', 'Package purchase recorded. Payment pending.', 'success');
          exit;
      } catch (Throwable $e) {
          redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Failed: ' . $e->getMessage(), 'error');
          exit;
      }
  }

  // Fetch current active package (if any)
  $current = null;
  try {
      $cst = db()->prepare('SELECT bp.*, p.package_name, p.package_type FROM bought_packages bp JOIN packages p ON p.package_id=bp.package_id WHERE bp.user_id=? AND bp.status="active" ORDER BY bp.start_date DESC LIMIT 1');
      $cst->bind_param('i', $uid);
      $cst->execute();
      $current = $cst->get_result()->fetch_assoc();
      $cst->close();
  } catch (Throwable $e) { /* ignore */ }

  // Fetch active packages
  $packages = [];
  try {
      $res = db()->query('SELECT * FROM packages WHERE status="active" ORDER BY price ASC');
      if ($res) { while ($row = $res->fetch_assoc()) { $packages[] = $row; } }
  } catch (Throwable $e) { $packages = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buy Advertising Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Buy Advertising Packages</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  

  <?php /* Flash/messages handled globally via SweetAlert2 in navbar; removed Bootstrap alerts */ ?>

  <?php if ($current): ?>
    <div class="card border-0 bg-light mb-3">
      <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong>Current Package:</strong> <?= htmlspecialchars((string)$current['package_name']) ?>
            <?php if (!empty($current['end_date'])): ?>
              <span class="ms-2">Ends: <?= htmlspecialchars((string)$current['end_date']) ?></span>
            <?php endif; ?>
            <?php $curTypeLbl = ((int)($current['remaining_rooms'] ?? 0) > 0 || (int)($current['max_rooms'] ?? 0) > 0) ? 'Room' : 'Property'; ?>
            <?php $curDurLbl = ((string)($current['package_type'] ?? 'monthly') === 'yearly') ? 'Yearly' : 'Monthly'; ?>
            <span class="ms-2">Type: <?= $curTypeLbl ?> | Duration: <?= $curDurLbl ?></span>
            <span class="ms-2">Remaining Properties: <?= (int)$current['remaining_properties'] ?> | Remaining Rooms: <?= (int)$current['remaining_rooms'] ?></span>
          </div>
          <span class="badge bg-secondary">Status: <?= htmlspecialchars((string)$current['payment_status']) ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php if (count($packages) === 0): ?>
      <div class="col-12">
        <div class="text-center text-muted py-4">No packages available right now.</div>
      </div>
    <?php endif; ?>
    <?php foreach ($packages as $pkg): ?>
      <?php
        $ptype = (string)$pkg['package_type'];
        $durLbl = ($ptype === 'yearly') ? 'Yearly' : 'Monthly';
        $mp = (int)($pkg['max_properties'] ?? 0);
        $mr = (int)($pkg['max_rooms'] ?? 0);
        $typeLbl = ($mr > 0) ? 'Room' : 'Property';
        $maxAdv = max($mp, $mr);
      ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title mb-0"><?= htmlspecialchars((string)$pkg['package_name']) ?></h5>
              <span class="badge text-bg-primary"><?= htmlspecialchars($durLbl) ?></span>
            </div>
            <p class="text-muted mb-2"><?= nl2br(htmlspecialchars((string)($pkg['description'] ?? ''))) ?></p>
            <ul class="list-unstyled small mb-3">
              <li><i class="bi bi-tags me-1"></i> Type: <?= htmlspecialchars($typeLbl) ?></li>
              <li><i class="bi bi-clock me-1"></i> Duration: <?= htmlspecialchars($durLbl) ?></li>
              <li><i class="bi bi-megaphone me-1"></i> Max Advertising Count: <?= (int)$maxAdv ?></li>
            </ul>
            <div class="fs-5 fw-semibold mb-3">LKR <?= number_format((float)$pkg['price'], 2) ?></div>
            <form method="post" class="mt-auto" enctype="multipart/form-data">
              <input type="hidden" name="package_id" value="<?= (int)$pkg['package_id'] ?>">
              <div class="mb-2">
                <label class="form-label">Upload Payment Slip (image/PDF)</label>
                <input type="file" name="payment_slip" accept="image/*,application/pdf" class="form-control" required>
                <div class="form-text">Your package will be pending until an admin verifies the slip.</div>
              </div>
              <button type="submit" class="btn btn-primary w-100">Submit Slip & Buy</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    try {
      <?php if (count($packages) === 0): ?>
      if (window.Swal) {
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true });
        Toast.fire({ icon: 'warning', title: 'No packages available right now.' });
      }
      <?php endif; ?>
    } catch (_) {}
  })();
  </script>
</body>
</html>

