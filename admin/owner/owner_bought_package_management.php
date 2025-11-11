<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
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
?>
<?php require_once __DIR__ . '/../../public/includes/auth_guard.php'; require_role('admin'); ?>
<?php require_once __DIR__ . '/../../config/config.php'; ?>
<?php
  $alert = ['type'=>'','msg'=>''];
  $expire_msg = '';
  [$flash, $flash_type] = get_flash();

  // Auto-expire packages whose end_date has passed
  try {
    $q = db()->query("UPDATE bought_packages SET status='expired' WHERE status='active' AND end_date IS NOT NULL AND end_date < NOW()");
    if ($q !== false) {
      $cnt = db()->affected_rows;
      if ($cnt > 0) { $expire_msg = $cnt . ' package(s) auto-expired based on end date.'; }
    }
  } catch (Throwable $e) { /* ignore auto-expire errors */ }

  // Handle POST with PRG
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
      $action = $_POST['action'] ?? '';
      $bp_id = (int)($_POST['bought_package_id'] ?? 0);
      if ($bp_id <= 0) { throw new Exception('Invalid id'); }
      if ($action === 'set_payment_status') {
        $ps = $_POST['payment_status'] ?? 'pending';
        if (!in_array($ps, ['pending','paid','failed'], true)) { throw new Exception('Bad status'); }
        $st = db()->prepare('UPDATE bought_packages SET payment_status=? WHERE bought_package_id=?');
        $st->bind_param('si', $ps, $bp_id);
        $st->execute();
        $st->close();
        redirect_with_message($GLOBALS['base_url'] . '/admin/owner/owner_bought_package_management.php', 'Payment status updated', 'success');
        exit;
      } elseif ($action === 'set_package_status') {
        $s = $_POST['status'] ?? 'active';
        if (!in_array($s, ['active','expired'], true)) { throw new Exception('Bad status'); }
        $st = db()->prepare('UPDATE bought_packages SET status=? WHERE bought_package_id=?');
        $st->bind_param('si', $s, $bp_id);
        $st->execute();
        $st->close();
        redirect_with_message($GLOBALS['base_url'] . '/admin/owner/owner_bought_package_management.php', 'Package status updated', 'success');
        exit;
      } else {
        throw new Exception('Unknown action');
      }
    } catch (Throwable $e) {
      redirect_with_message($GLOBALS['base_url'] . '/admin/owner/owner_bought_package_management.php', 'Failed: ' . $e->getMessage(), 'error');
      exit;
    }
  }

  // Filters (querystring)
  $q = trim($_GET['q'] ?? '');
  $f_status = $_GET['status'] ?? '';
  $f_pay = $_GET['payment_status'] ?? '';

  // Quick stats
  $stat_total = 0; $stat_active = 0; $stat_expired = 0; $stat_pending = 0;
  try {
    $r1 = db()->query("SELECT COUNT(*) c FROM bought_packages");
    if ($r1) { $stat_total = (int)$r1->fetch_assoc()['c']; }
    $r2 = db()->query("SELECT COUNT(*) c FROM bought_packages WHERE status='active'");
    if ($r2) { $stat_active = (int)$r2->fetch_assoc()['c']; }
    $r3 = db()->query("SELECT COUNT(*) c FROM bought_packages WHERE status='expired'");
    if ($r3) { $stat_expired = (int)$r3->fetch_assoc()['c']; }
    $r4 = db()->query("SELECT COUNT(*) c FROM bought_packages WHERE payment_status='pending'");
    if ($r4) { $stat_pending = (int)$r4->fetch_assoc()['c']; }
  } catch (Throwable $e) {}

  // Load bought packages with filters + pagination
  $rows = [];
  try {
    $perPage = max(5, (int)($_GET['per'] ?? 20));
    if ($perPage > 100) { $perPage = 100; }
    $page = max(1, (int)($_GET['p'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $where = [];
    if ($q !== '') {
      $qq = db()->real_escape_string($q);
      $where[] = "(u.name LIKE '%$qq%' OR u.email LIKE '%$qq%' OR p.package_name LIKE '%$qq%')";
    }
    if (in_array($f_status, ['active','expired'], true)) {
      $where[] = "bp.status='" . db()->real_escape_string($f_status) . "'";
    }
    if (in_array($f_pay, ['pending','paid','failed'], true)) {
      $where[] = "bp.payment_status='" . db()->real_escape_string($f_pay) . "'";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $totalFiltered = 0;
    $csql = "SELECT COUNT(*) c FROM bought_packages bp JOIN users u ON u.user_id=bp.user_id JOIN packages p ON p.package_id=bp.package_id $whereSql";
    $cres = db()->query($csql);
    if ($cres) { $totalFiltered = (int)$cres->fetch_assoc()['c']; }

    $sql = "
      SELECT bp.bought_package_id, bp.user_id, u.name AS user_name, u.email AS user_email,
             p.package_name, p.package_type, bp.start_date, bp.end_date,
             bp.remaining_properties, bp.remaining_rooms, bp.status, bp.payment_status,
             (
               SELECT payment_reference FROM package_payments pp
               WHERE pp.bought_package_id = bp.bought_package_id
               ORDER BY pp.payment_id DESC LIMIT 1
             ) AS last_slip,
             (
               SELECT amount FROM package_payments pp2
               WHERE pp2.bought_package_id = bp.bought_package_id
               ORDER BY pp2.payment_id DESC LIMIT 1
             ) AS last_amount
      FROM bought_packages bp
      JOIN users u ON u.user_id = bp.user_id
      JOIN packages p ON p.package_id = bp.package_id
      $whereSql
      ORDER BY bp.bought_package_id DESC
      LIMIT $offset, $perPage
    ";
    $res = db()->query($sql);
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
  } catch (Throwable $e) { $rows = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Bought Packages</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
  </div>

  <?php /* Alerts handled by SweetAlert2 via JS below */ ?>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="card border-primary"><div class="card-body">
        <div class="text-muted small">Total</div>
        <div class="fs-4 fw-semibold"><?= (int)$stat_total ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Active</div>
        <div class="fs-4 fw-semibold text-success"><?= (int)$stat_active ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Expired</div>
        <div class="fs-4 fw-semibold text-secondary"><?= (int)$stat_expired ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Pending Payments</div>
        <div class="fs-4 fw-semibold text-warning"><?= (int)$stat_pending ?></div>
      </div></div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-12 col-md-4">
          <label class="form-label">Search</label>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="User, email, package name">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="active" <?= $f_status==='active'?'selected':'' ?>>Active</option>
            <option value="expired" <?= $f_status==='expired'?'selected':'' ?>>Expired</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Payment</label>
          <select name="payment_status" class="form-select">
            <option value="">All</option>
            <option value="pending" <?= $f_pay==='pending'?'selected':'' ?>>Pending</option>
            <option value="paid" <?= $f_pay==='paid'?'selected':'' ?>>Paid</option>
            <option value="failed" <?= $f_pay==='failed'?'selected':'' ?>>Failed</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-3">All Bought Packages</h5>
      <div class="table-responsive">
        <table class="table align-middle table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Package</th>
              <th>Duration</th>
              <th>Remaining</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Slip</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $durLbl = ((string)($r['package_type'] ?? 'monthly') === 'yearly') ? 'Yearly' : 'Monthly';
              ?>
              <tr>
                <td><?= (int)$r['bought_package_id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars((string)($r['user_name'] ?? '')) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars((string)($r['user_email'] ?? '')) ?></div>
                </td>
                <td><?= htmlspecialchars((string)($r['package_name'] ?? '')) ?></td>
                <td>
                  <div><?= $durLbl ?></div>
                  <div class="text-muted small">Start: <?= htmlspecialchars((string)$r['start_date']) ?></div>
                  <?php if (!empty($r['end_date'])): ?><div class="text-muted small">End: <?= htmlspecialchars((string)$r['end_date']) ?></div><?php endif; ?>
                </td>
                <td>
                  <div class="small">Properties: <?= (int)$r['remaining_properties'] ?></div>
                  <div class="small">Rooms: <?= (int)$r['remaining_rooms'] ?></div>
                </td>
                <td>
                  <span class="badge <?= ($r['status']==='active'?'text-bg-success':'text-bg-secondary') ?>"><?= htmlspecialchars((string)$r['status']) ?></span>
                </td>
                <td>
                  <div class="small">Status: <span class="badge <?= ($r['payment_status']==='paid'?'text-bg-success':($r['payment_status']==='failed'?'text-bg-danger':'text-bg-warning text-dark')) ?>"><?= htmlspecialchars((string)$r['payment_status']) ?></span></div>
                  <?php if (!is_null($r['last_amount'])): ?>
                    <div class="small">Amount: LKR <?= number_format((float)$r['last_amount'],2) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($r['last_slip'])): ?>
                    <?php
                      $sl = (string)$r['last_slip'];
                      if ($sl !== '') {
                        if (!preg_match('#^https?://#i', $sl)) {
                          if ($sl[0] === '/') { $sl = rtrim($base_url, '/') . $sl; }
                          else { $sl = rtrim($base_url, '/') . '/' . ltrim($sl, '/'); }
                        }
                      }
                    ?>
                    <a href="<?= htmlspecialchars($sl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Slip</a>
                  <?php else: ?>
                    <span class="text-muted small">No slip</span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline bp-payment-form">
                    <input type="hidden" name="action" value="set_payment_status">
                    <input type="hidden" name="bought_package_id" value="<?= (int)$r['bought_package_id'] ?>">
                    <select name="payment_status" class="form-select form-select-sm d-inline-block" style="width: 120px;">
                      <?php $ps = (string)($r['payment_status'] ?? 'pending'); ?>
                      <option value="pending" <?= $ps==='pending'?'selected':'' ?>>Pending</option>
                      <option value="paid" <?= $ps==='paid'?'selected':'' ?>>Paid</option>
                      <option value="failed" <?= $ps==='failed'?'selected':'' ?>>Failed</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="9" class="text-center py-4">No records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php
        $totalPages = isset($totalFiltered) && $perPage > 0 ? (int)ceil($totalFiltered / $perPage) : 1;
        if ($totalPages < 1) { $totalPages = 1; }
      ?>
      <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination">
          <ul class="pagination justify-content-end mb-0">
            <?php $base = $_SERVER['PHP_SELF'] . '?q=' . urlencode($q) . '&status=' . urlencode($f_status) . '&payment_status=' . urlencode($f_pay) . '&per=' . $perPage; ?>
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="<?= $page>1 ? ($base . '&p=' . ($page-1)) : '#' ?>">Prev</a>
            </li>
            <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="<?= $base . '&p=' . $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="<?= $page<$totalPages ? ($base . '&p=' . ($page+1)) : '#' ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    try {
      const expireMsg = <?= json_encode($expire_msg) ?>;
      if (expireMsg) {
        Swal.fire({ icon: 'info', title: 'Info', text: String(expireMsg), confirmButtonText: 'OK' });
      }
      document.querySelectorAll('form.bp-payment-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const sel = form.querySelector('select[name="payment_status"]');
          const to = sel ? sel.value : '';
          const res = await Swal.fire({
            title: 'Update payment status?',
            text: to ? ('Set payment status to ' + to + '?') : 'Update payment status?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update',
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

