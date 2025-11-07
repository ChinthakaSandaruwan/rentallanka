<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('customer');
require_once __DIR__ . '/../config/config.php';

$customer_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($customer_id <= 0) {
  redirect_with_message('../auth/login.php', 'Please login', 'error');
}

// Handle slip delete (D)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_slip') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $slip_id = (int)($_POST['slip_id'] ?? 0);
    if ($slip_id <= 0) {
      $err = 'Bad input';
    } else {
      // Verify slip belongs to this customer via rentals
      $chk = db()->prepare('SELECT ps.slip_id FROM payment_slips ps JOIN rentals r ON r.rental_id = ps.rental_id WHERE ps.slip_id=? AND r.customer_id=?');
      $chk->bind_param('ii', $slip_id, $customer_id);
      $chk->execute();
      $has = $chk->get_result()->fetch_assoc();
      $chk->close();
      if (!$has) {
        $err = 'Slip not found';
      } else {
        $del = db()->prepare('DELETE FROM payment_slips WHERE slip_id=?');
        $del->bind_param('i', $slip_id);
        if ($del->execute() && $del->affected_rows > 0) { $ok = 'Slip deleted'; } else { $err = 'Delete failed'; }
        $del->close();
      }
    }
  }
}

// Handle slip replace (U)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'replace_slip') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $slip_id = (int)($_POST['slip_id'] ?? 0);
    $for_month = trim($_POST['for_month'] ?? '');
    if ($slip_id <= 0) {
      $err = 'Bad input';
    } elseif ($for_month === '' || !preg_match('/^\d{4}-\d{2}$/', $for_month)) {
      $err = 'Please pick a month (YYYY-MM)';
    } elseif (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
      $err = 'Please choose a file';
    } else {
      // Verify slip belongs to this customer and get rental
      $chk = db()->prepare('SELECT ps.slip_id, ps.rental_id FROM payment_slips ps JOIN rentals r ON r.rental_id = ps.rental_id WHERE ps.slip_id=? AND r.customer_id=?');
      $chk->bind_param('ii', $slip_id, $customer_id);
      $chk->execute();
      $row = $chk->get_result()->fetch_assoc();
      $chk->close();
      if (!$row) {
        $err = 'Slip not found';
      } else {
        $rental_id = (int)$row['rental_id'];
        $projectRoot = realpath(__DIR__ . '/..');
        $targetDir = $projectRoot . '/uploads/payment_slips/properties';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
        $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        // Use chosen month encoded in filename (YYYYMM)
        $yyyymm = str_replace('-', '', substr($for_month, 0, 7));
        $fname = 'property_slip_' . $rental_id . '_' . $yyyymm . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($safeExt ? ('.' . $safeExt) : '');
        $dest = $targetDir . '/' . $fname;
        if (move_uploaded_file($_FILES['slip']['tmp_name'], $dest)) {
          $url = rtrim($GLOBALS['base_url'], '/') . '/uploads/payment_slips/properties/' . $fname;
          $up = db()->prepare('UPDATE payment_slips SET slip_path=?, uploaded_at=NOW() WHERE slip_id=?');
          $up->bind_param('si', $url, $slip_id);
          if ($up->execute()) { $ok = 'Slip updated'; } else { $err = 'Update failed'; }
          $up->close();
        } else {
          $err = 'Failed to upload file';
        }
      }
    }
  }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';

// Helper: bind params by reference for dynamic IN queries
if (!function_exists('stmt_bind_params_array')) {
  function stmt_bind_params_array(mysqli_stmt $stmt, string $types, array &$values): bool {
    $params = array_merge([$types], $values);
    $refs = [];
    foreach ($params as $k => &$v) { $refs[$k] = &$v; }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
  }
}

// Handle slip upload for an active property rental (monthly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_slip') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $rental_id = (int)($_POST['rental_id'] ?? 0);
    $for_month = trim($_POST['for_month'] ?? ''); // format YYYY-MM
    if ($rental_id <= 0) {
      $err = 'Bad input';
    } elseif ($for_month === '' || !preg_match('/^\d{4}-\d{2}$/', $for_month)) {
      $err = 'Please pick a month (YYYY-MM)';
    } elseif (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
      $err = 'Please choose a file';
    } else {
      // Verify rental belongs to customer and is active
      $chk = db()->prepare('SELECT rental_id FROM rentals WHERE rental_id=? AND customer_id=? AND status="active"');
      $chk->bind_param('ii', $rental_id, $customer_id);
      $chk->execute();
      $has = $chk->get_result()->fetch_assoc();
      $chk->close();
      if (!$has) {
        $err = 'Rental not found or inactive';
      } else {
        $projectRoot = realpath(__DIR__ . '/..');
        $targetDir = $projectRoot . '/uploads/payment_slips/properties';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
        $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $yyyymm = str_replace('-', '', substr($for_month, 0, 7)); // YYYYMM
        $fname = 'property_slip_' . $rental_id . '_' . $yyyymm . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($safeExt ? ('.' . $safeExt) : '');
        $dest = $targetDir . '/' . $fname;
        if (move_uploaded_file($_FILES['slip']['tmp_name'], $dest)) {
          $url = rtrim($GLOBALS['base_url'], '/') . '/uploads/payment_slips/properties/' . $fname;
          // Use payment_slips table referencing rentals
          $ins = db()->prepare('INSERT INTO payment_slips (rental_id, slip_path) VALUES (?, ?)');
          $ins->bind_param('is', $rental_id, $url);
          if ($ins->execute()) { $ok = 'Slip uploaded'; } else { $err = 'Failed to save slip'; }
          $ins->close();
        } else {
          $err = 'Failed to upload file';
        }
      }
    }
  }
}

// Load customer's property rentals (active first)
$rows = [];
$sql = 'SELECT r.rental_id, r.property_id, r.start_date, r.end_date, r.total_price, r.status, r.created_at, p.title
        FROM rentals r
        JOIN properties p ON p.property_id = r.property_id
        WHERE r.customer_id = ?
        ORDER BY (r.status="active") DESC, r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$stmt->close();

// Fetch slip counts per rental
$slipCounts = [];
// Fetch slip details per rental for history rendering
$slipsByRental = [];
if ($rows) {
  $rentalIds = array_unique(array_map(fn($r) => (int)$r['rental_id'], $rows));
  if ($rentalIds) {
    $in = implode(',', array_fill(0, count($rentalIds), '?'));
    $types = str_repeat('i', count($rentalIds));
    $q = db()->prepare('SELECT rental_id, COUNT(*) AS c FROM payment_slips WHERE rental_id IN (' . $in . ') GROUP BY rental_id');
    stmt_bind_params_array($q, $types, $rentalIds);
    $q->execute();
    $rs = $q->get_result();
    while ($r = $rs->fetch_assoc()) { $slipCounts[(int)$r['rental_id']] = (int)$r['c']; }
    $q->close();

    // Load slip records for history display
    $q2 = db()->prepare('SELECT slip_id, rental_id, slip_path, uploaded_at FROM payment_slips WHERE rental_id IN (' . $in . ') ORDER BY uploaded_at DESC');
    stmt_bind_params_array($q2, $types, $rentalIds);
    $q2->execute();
    $rs2 = $q2->get_result();
    while ($s = $rs2->fetch_assoc()) {
      $rid = (int)$s['rental_id'];
      if (!isset($slipsByRental[$rid])) { $slipsByRental[$rid] = []; }
      $slipsByRental[$rid][] = $s;
    }
    $q2->close();
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Property Monthly Payments</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">My Property Monthly Payments</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Property</th>
              <th>Period</th>
              <th>Status</th>
              <th>Slips</th>
              <th class="text-end">Upload Slip</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): $isActive = ($r['status'] === 'active'); ?>
              <tr>
                <td><?php echo (int)$r['rental_id']; ?></td>
                <td><?php echo htmlspecialchars($r['title']); ?></td>
                <td><?php echo htmlspecialchars($r['start_date']); ?> → <?php echo htmlspecialchars($r['end_date'] ?? ''); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td>
                  <?php $cnt = $slipCounts[(int)$r['rental_id']] ?? 0; ?>
                  <span class="badge bg-info text-dark"><?php echo (int)$cnt; ?></span>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#manage-<?php echo (int)$r['rental_id']; ?>" aria-expanded="false" aria-controls="manage-<?php echo (int)$r['rental_id']; ?>">Manage</button>
                </td>
              </tr>
              <tr class="table-active">
                <td colspan="6" class="py-3">
                  <?php $rid = (int)$r['rental_id']; $tabId = 'manage-' . $rid; $tab1 = 'tab-upload-' . $rid; $tab2 = 'tab-history-' . $rid; ?>
                  <div id="<?php echo $tabId; ?>" class="collapse">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="<?php echo $tab1; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $tab1; ?>" type="button" role="tab" aria-controls="<?php echo $tab1; ?>" aria-selected="true">Step 1 · Upload Slip</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="<?php echo $tab2; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $tab2; ?>" type="button" role="tab" aria-controls="<?php echo $tab2; ?>" aria-selected="false">Step 2 · Payment History</button>
                      </li>
                    </ul>
                    <div class="tab-content">
                      <div class="tab-pane fade show active" id="<?php echo $tab1; ?>" role="tabpanel" aria-labelledby="<?php echo $tab1; ?>-tab">
                        <?php if ($isActive): ?>
                          <form method="post" enctype="multipart/form-data" class="row gy-2 gx-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="upload_slip">
                            <input type="hidden" name="rental_id" value="<?php echo $rid; ?>">
                            <div class="col-sm-3">
                              <label class="form-label mb-1 small">Month</label>
                              <input class="form-control form-control-sm" type="month" name="for_month" required>
                            </div>
                            <div class="col-sm-6">
                              <label class="form-label mb-1 small">Slip File</label>
                              <input class="form-control form-control-sm" type="file" name="slip" required>
                            </div>
                            <div class="col-sm-3">
                              <button type="submit" class="btn btn-sm btn-primary w-100">Upload</button>
                            </div>
                          </form>
                        <?php else: ?>
                          <div class="alert alert-warning mb-0">Rental is not active.</div>
                        <?php endif; ?>
                      </div>
                      <div class="tab-pane fade" id="<?php echo $tab2; ?>" role="tabpanel" aria-labelledby="<?php echo $tab2; ?>-tab">
                        <div class="small text-muted mb-2">Payment history</div>
                        <ul class="list-group list-group-flush">
                          <?php foreach (($slipsByRental[$rid] ?? []) as $s): ?>
                            <?php
                              $href = $s['slip_path'] ?? '';
                              $uploaded_at = $s['uploaded_at'] ?? '';
                              $sid = (int)($s['slip_id'] ?? 0);
                              // Prefer month from filename pattern: ..._{YYYYMM}_...
                              $label = '';
                              $ym = null;
                              if ($href) {
                                $base = basename(parse_url($href, PHP_URL_PATH));
                                if (preg_match('/_(\d{6})_/', $base, $m)) { $ym = $m[1]; }
                              }
                              if ($ym) {
                                $y = (int)substr($ym, 0, 4); $mnum = (int)substr($ym, 4, 2);
                                $label = date('F Y', strtotime(sprintf('%04d-%02d-01', $y, $mnum)));
                              } else {
                                $ts = $uploaded_at ? strtotime($uploaded_at) : false;
                                $label = $ts ? date('F Y', $ts) : 'Uploaded';
                              }
                            ?>
                            <li class="list-group-item px-0">
                              <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                <span class="me-auto"><?php echo htmlspecialchars($label); ?></span>
                                <div class="d-flex gap-2">
                                  <?php if ($href): ?>
                                    <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                                  <?php endif; ?>
                                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this slip?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_slip">
                                    <input type="hidden" name="slip_id" value="<?php echo $sid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                  </form>
                                  <form method="post" enctype="multipart/form-data" class="d-inline-flex gap-2 align-items-center">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="replace_slip">
                                    <input type="hidden" name="slip_id" value="<?php echo $sid; ?>">
                                    <input class="form-control form-control-sm" type="month" name="for_month" placeholder="YYYY-MM">
                                    <input class="form-control form-control-sm" type="file" name="slip" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Replace</button>
                                  </form>
                                </div>
                              </div>
                            </li>
                          <?php endforeach; ?>
                          <?php if (empty($slipsByRental[$rid])): ?>
                            <li class="list-group-item px-0 text-muted">No slips yet.</li>
                          <?php endif; ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="text-center py-4">No property rentals found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
