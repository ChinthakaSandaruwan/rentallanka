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

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$rows = [];
try {
    $sql = 'SELECT bp.*, p.package_name, p.package_type, p.duration_days, p.max_properties, p.max_rooms, p.price
            FROM bought_packages bp
            JOIN packages p ON p.package_id = bp.package_id
            WHERE bp.user_id = ?
            ORDER BY bp.bought_package_id DESC';
    $st = db()->prepare($sql);
    $st->bind_param('i', $uid);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $st->close();
} catch (Throwable $e) { $rows = []; }

[$flash, $flash_type] = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Advertising Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">My Advertising Packages</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert <?= $flash_type==='success'?'alert-success':'alert-danger' ?>" role="alert"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="alert alert-info">
    <div class="d-flex">
      <i class="bi bi-info-circle me-2"></i>
      <div>
        Posting a property or room will automatically deduct one slot from the relevant purchased package
        (Property slot or Room slot). Only <strong>paid</strong> and <strong>active</strong> packages can be used.
      </div>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($rows as $r): ?>
      <?php
        $typeLbl = ((int)($r['max_properties'] ?? 0) > 0) ? 'Property' : 'Room';
        $durLbl = ((string)($r['package_type'] ?? 'monthly') === 'yearly') ? 'Yearly' : 'Monthly';
        $remProps = (int)($r['remaining_properties'] ?? 0);
        $remRooms = (int)($r['remaining_rooms'] ?? 0);
        $isActive = ((string)($r['status'] ?? '') === 'active');
        $isPaid = ((string)($r['payment_status'] ?? '') === 'paid');
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="card-title mb-0"><?= htmlspecialchars((string)($r['package_name'] ?? '')) ?></h5>
              <span class="badge bg-secondary"><?= htmlspecialchars($durLbl) ?></span>
            </div>
            <ul class="list-unstyled small mb-3">
              <li><i class="bi bi-megaphone me-1"></i> Type: <?= htmlspecialchars($typeLbl) ?></li>
              <li><i class="bi bi-tags me-1"></i> Price: LKR <?= number_format((float)($r['price'] ?? 0), 2) ?></li>
              <?php if (!empty($r['end_date'])): ?>
                <li><i class="bi bi-calendar2-check me-1"></i> Ends: <?= htmlspecialchars((string)$r['end_date']) ?></li>
              <?php endif; ?>
            </ul>
            <div class="mb-2">
              <span class="badge <?= $isActive?'bg-success':'bg-secondary' ?>">Status: <?= htmlspecialchars((string)$r['status']) ?></span>
              <span class="badge <?= $isPaid?'bg-success':'bg-warning text-dark' ?>">Payment: <?= htmlspecialchars((string)$r['payment_status']) ?></span>
            </div>
            <div class="mt-auto">
              <div class="border rounded p-2 small">
                <div class="fw-semibold mb-1">Remaining Quotas</div>
                <div>Properties: <?= $remProps ?></div>
                <div>Rooms: <?= $remRooms ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <div class="col-12">
        <div class="card">
          <div class="card-body text-center">
            <p class="mb-2">You have not purchased any advertising packages yet.</p>
            <a href="buy_advertising_packages.php" class="btn btn-primary"><i class="bi bi-bag-plus me-1"></i>Buy a Package</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
