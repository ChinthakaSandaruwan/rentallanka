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

          $slipDir = __DIR__ . '/../../uploads/package_slips';
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
  // Fetch bank details
  $banks = [];
  try {
      $bres = db()->query('SELECT bank_name, branch, account_number, account_holder_name FROM bank_details ORDER BY bank_id DESC');
      if ($bres) { while ($row = $bres->fetch_assoc()) { $banks[] = $row; } }
  } catch (Throwable $e) { $banks = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buy Advertising Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       BUY PACKAGES PAGE CUSTOM STYLES
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
      --rl-shadow-lg: 0 10px 30px rgba(0,0,0,.15);
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
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border-radius: var(--rl-radius-lg);
      padding: 1.5rem 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--rl-shadow-md);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 1.875rem);
      font-weight: 800;
      color: var(--rl-white);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-btn-back {
      background: var(--rl-white);
      border: none;
      color: var(--rl-primary);
      font-weight: 600;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-btn-back:hover {
      background: var(--rl-light-bg);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      color: var(--rl-primary);
    }
    
    /* Form Cards */
    .rl-form-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-md);
      border: 2px solid var(--rl-border);
      overflow: hidden;
    }
    
    .rl-form-header {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      padding: 1.25rem 1.5rem;
      border-bottom: 2px solid var(--rl-border);
    }
    
    .rl-form-header-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-form-body {
      padding: 1.5rem;
    }
    
    /* Current Package Card */
    .rl-current-package {
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      border-color: var(--rl-accent);
    }
    
    /* Form Inputs */
    .form-label {
      font-weight: 600;
      color: var(--rl-text);
      margin-bottom: 0.5rem;
      font-size: 0.9375rem;
    }
    
    .form-control,
    .form-select {
      border: 2px solid var(--rl-border);
      border-radius: 10px;
      padding: 0.625rem 0.875rem;
      font-size: 0.9375rem;
      color: var(--rl-text);
      background: var(--rl-white);
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .form-control:focus,
    .form-select:focus {
      border-color: var(--rl-primary);
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
      outline: none;
    }
    
    .form-control:hover:not(:focus) {
      border-color: #cbd5e0;
    }
    
    /* File Input */
    input[type="file"].form-control {
      padding: 0.5rem 0.875rem;
      cursor: pointer;
    }
    
    .form-text {
      font-size: 0.8125rem;
      color: var(--rl-text-muted);
    }
    
    /* Badges */
    .rl-badge-accent {
      background: linear-gradient(135deg, var(--rl-accent) 0%, var(--rl-primary) 100%);
      color: var(--rl-white);
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.8125rem;
      letter-spacing: 0.5px;
    }
    
    .rl-badge-dark {
      background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8533 100%);
      color: var(--rl-white);
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.8125rem;
      letter-spacing: 0.5px;
    }
    
    /* Package Cards */
    .pkg-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }
    
    .pkg-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .pkg-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--rl-shadow-lg);
      border-color: var(--rl-accent) !important;
    }
    
    .pkg-card:hover::before {
      opacity: 1;
    }
    
    .pkg-title {
      font-weight: 800;
      font-size: 1.25rem;
      color: var(--rl-text);
    }
    
    .pkg-type {
      font-weight: 700;
      text-transform: uppercase;
    }
    
    .rl-price {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--rl-dark);
      letter-spacing: -0.5px;
    }
    
    /* Package Features List */
    .pkg-card ul {
      padding-left: 0;
    }
    
    .pkg-card ul li {
      padding: 0.375rem 0;
      color: var(--rl-text-secondary);
      font-weight: 500;
    }
    
    .pkg-card ul li i {
      color: var(--rl-accent);
      font-size: 1rem;
    }
    
    /* Buttons */
    .btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border: none;
      color: var(--rl-white);
      font-weight: 700;
      padding: 0.875rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.25);
      transition: all 0.2s ease;
      font-size: 0.9375rem;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: var(--rl-white);
    }
    
    .btn-primary:active {
      transform: translateY(0);
    }
    
    /* Bank Table */
    .table {
      margin-bottom: 0;
    }
    
    .table thead th {
      background: #f8fafc;
      border-bottom: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
      font-weight: 700;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 0.875rem 0.75rem;
    }
    
    .table tbody tr {
      border-bottom: 1px solid var(--rl-border);
      transition: background-color 0.2s ease;
    }
    
    .table tbody tr:hover {
      background: rgba(0, 78, 152, 0.02);
    }
    
    .table tbody tr:last-child {
      border-bottom: none;
    }
    
    .table tbody td {
      padding: 0.875rem 0.75rem;
      vertical-align: middle;
      font-size: 0.9375rem;
    }
    
    /* Empty State */
    .rl-empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-sm);
      border: 2px dashed var(--rl-border);
    }
    
    .rl-empty-state i {
      font-size: 3rem;
      color: var(--rl-secondary);
      margin-bottom: 1rem;
    }
    
    /* Responsive */
    @media (max-width: 991px) {
      .pkg-card {
        margin-bottom: 1rem;
      }
    }
    
    @media (max-width: 767px) {
      .rl-page-header {
        padding: 1.25rem 1rem;
        flex-direction: column;
        align-items: flex-start;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
      }
      
      .rl-btn-back {
        width: 100%;
        justify-content: center;
      }
      
      .rl-form-body {
        padding: 1rem;
      }
      
      .btn-primary {
        width: 100%;
        padding: 0.75rem 1.25rem;
      }
      
      .table {
        font-size: 0.875rem;
      }
      
      .table thead th,
      .table tbody td {
        padding: 0.625rem 0.5rem;
      }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-bag-plus"></i> Buy Advertising Packages</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>


  <?php /* Flash/messages handled globally via SweetAlert2 in navbar; removed Bootstrap alerts */ ?>

  <?php if ($current): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-body py-3">
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
          <span class="badge rl-badge-accent">Status: <?= htmlspecialchars((string)$current['payment_status']) ?></span>
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
        <div class="rl-form-card h-100 pkg-card">
          <div class="rl-form-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="pkg-title mb-0"><?= htmlspecialchars((string)$pkg['package_name']) ?></h5>
              <span class="badge rl-badge-dark pkg-type"><?= htmlspecialchars($durLbl) ?></span>
            </div>
            <p class="text-muted mb-2"><?= nl2br(htmlspecialchars((string)($pkg['description'] ?? ''))) ?></p>
            <ul class="list-unstyled small mb-3">
              <li><i class="bi bi-tags me-1"></i> Type: <?= htmlspecialchars($typeLbl) ?></li>
              <li><i class="bi bi-clock me-1"></i> Duration: <?= htmlspecialchars($durLbl) ?></li>
              <li><i class="bi bi-megaphone me-1"></i> Max Advertising Count: <?= (int)$maxAdv ?></li>
            </ul>
            <div class="rl-price mb-3">LKR <?= number_format((float)$pkg['price'], 2) ?></div>
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
  <?php if (!empty($banks)): ?>
  <div class="rl-form-card my-4">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-bank"></i> Bank Details for Payments</h2></div>
    <div class="rl-form-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Bank</th>
              <th>Branch</th>
              <th>Account Number</th>
              <th>Account Holder</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($banks as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['bank_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($b['branch'] ?? '') ?></td>
                <td><span class="fw-semibold"><?= htmlspecialchars($b['account_number'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($b['account_holder_name'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted small">Make the payment to one of the above accounts and upload the payment slip below.</div>
    </div>
  </div>
  <?php endif; ?>
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

