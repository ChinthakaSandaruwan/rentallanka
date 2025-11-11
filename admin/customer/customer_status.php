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

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$user_id = (int)($_GET['user_id'] ?? 0);
$role = 'customer';
$error = '';
$flash = '';
$flash_type = 'success';

// Load existing customer
$customer = null;
if ($user_id > 0) {
  $stmt = db()->prepare('SELECT user_id, name, email, phone, status, profile_image FROM users WHERE user_id = ? AND role = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('is', $user_id, $role);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $customer = $res->fetch_assoc();
      $res->free();
    }
    $stmt->close();
  }
}

if ($user_id > 0 && !$customer) {
  $error = 'Invalid customer id';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $new_status = $_POST['status'] ?? '';
    if (!in_array($new_status, ['active','inactive','banned'], true)) {
      $error = 'Invalid status value';
    } elseif ($user_id <= 0) {
      $error = 'Invalid customer id';
    } else {
      $stmt = db()->prepare('UPDATE users SET status = ? WHERE user_id = ? AND role = ?');
      if ($stmt) {
        $stmt->bind_param('sis', $new_status, $user_id, $role);
        if ($stmt->execute()) {
          $flash = 'Status updated';
          $flash_type = 'success';
          $stmt->close();
          // Reload customer
          $stmt2 = db()->prepare('SELECT user_id, name, email, phone, status, profile_image FROM users WHERE user_id = ? AND role = ? LIMIT 1');
          if ($stmt2) {
            $stmt2->bind_param('is', $user_id, $role);
            if ($stmt2->execute()) {
              $res2 = $stmt2->get_result();
              $customer = $res2->fetch_assoc();
              $res2->free();
            }
            $stmt2->close();
          }
        } else {
          $error = 'Update failed';
          $stmt->close();
        }
      } else {
        $error = 'Update failed';
      }
    }
  }
  // POST-Redirect-GET to avoid resubmission on refresh
  $msg = $flash ?: ($error ?: 'Action completed.');
  $typ = $flash ? ($flash_type ?: 'success') : ($error ? 'error' : 'success');
  $url = rtrim($base_url,'/') . '/admin/customer/customer_status.php?user_id=' . (int)$user_id;
  redirect_with_message($url, $msg, $typ);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Change Customer Status</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <?php /* Alerts handled by SweetAlert2 via JS below */ ?>

    <?php if ($customer): ?>
    <div class="card">
      <div class="card-header">Customer</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <?php $img = (string)($customer['profile_image'] ?? ''); ?>
          <?php if ($img !== ''): ?>
            <img src="<?php echo $base_url . '/' . ltrim($img, '/'); ?>" alt="Profile" class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:64px;height:64px;color:white;">
              <span style="font-weight:600;">No Image</span>
            </div>
          <?php endif; ?>
          <div>
            <div class="fw-semibold"><?php echo htmlspecialchars($customer['name'] ?? ''); ?></div>
            <div class="text-muted small"><?php echo htmlspecialchars($customer['email'] ?? ''); ?> · <?php echo htmlspecialchars($customer['phone'] ?? ''); ?></div>
          </div>
        </div>

        <form method="post" class="row g-3" id="formStatus">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="col-12 col-md-6">
            <label for="status" class="form-label">Status<span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select" required>
              <?php $st = $customer['status'] ?? 'active'; ?>
              <option value="active" <?php echo $st==='active'?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo $st==='inactive'?'selected':''; ?>>Inactive</option>
              <option value="banned" <?php echo $st==='banned'?'selected':''; ?>>Banned</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a class="btn btn-outline-secondary" href="customer_status.php">Back</a>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
      <?php
        // Filters and list when no specific user is selected
        $q = trim($_GET['q'] ?? '');
        $status_filter = $_GET['status'] ?? '';
        $wheres = ["role='customer'"];
        $params = [];
        $types = '';
        if ($q !== '') {
          $wheres[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR nic LIKE ?)";
          $like = '%' . $q . '%';
          $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
          $types .= 'ssss';
        }
        if (in_array($status_filter, ['active','inactive','banned'], true)) {
          $wheres[] = 'status = ?';
          $params[] = $status_filter;
          $types .= 's';
        }
        $sql = 'SELECT user_id, name, email, phone, status FROM users WHERE ' . implode(' AND ', $wheres) . ' ORDER BY user_id DESC';
        $customers = [];
        if ($types !== '') {
          $stmtL = db()->prepare($sql);
          if ($stmtL) {
            $stmtL->bind_param($types, ...$params);
            if ($stmtL->execute()) {
              $resL = $stmtL->get_result();
              while ($row = $resL->fetch_assoc()) { $customers[] = $row; }
              $resL->free();
            }
            $stmtL->close();
          }
        } else {
          $result = db()->query($sql);
          if ($result) {
            while ($row = $result->fetch_assoc()) { $customers[] = $row; }
            $result->close();
          }
        }
      ?>
      <div class="card">
        <div class="card-header">Customers</div>
        <div class="card-body p-0">
          <form method="get" class="p-3 border-bottom bg-light">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-6">
                <label class="form-label" for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="name, email, phone, NIC" value="<?php echo htmlspecialchars($q ?? ''); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                  <option value="">Any</option>
                  <option value="active" <?php echo ($status_filter==='active')?'selected':''; ?>>Active</option>
                  <option value="inactive" <?php echo ($status_filter==='inactive')?'selected':''; ?>>Inactive</option>
                  <option value="banned" <?php echo ($status_filter==='banned')?'selected':''; ?>>Banned</option>
                </select>
              </div>
              <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="customer_status.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
              </div>
            </div>
          </form>
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 80px;">ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th style="width: 120px;">Status</th>
                  <th style="width: 160px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($customers)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr>
                <?php else: ?>
                  <?php foreach ($customers as $c): ?>
                    <tr>
                      <td><?php echo (int)$c['user_id']; ?></td>
                      <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($c['email'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($c['phone'] ?? ''); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $c['status']==='active'?'success':($c['status']==='inactive'?'secondary':'danger'); ?>">
                          <?php echo htmlspecialchars($c['status']); ?>
                        </span>
                      </td>
                      <td>
                        <a class="btn btn-sm btn-outline-primary" href="customer_status.php?user_id=<?php echo (int)$c['user_id']; ?>">
                          <i class="bi bi-arrow-repeat me-1"></i>Change Status
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script>
    (function(){
      try {
        const err = <?= json_encode($error) ?>;
        const msg = <?= json_encode($flash) ?>;
        const typ = (<?= json_encode($flash_type) ?> || 'info').toLowerCase();
        const icon = ({ success:'success', error:'error', danger:'error', warning:'warning', info:'info' })[typ] || 'info';
        if (err) {
          Swal.fire({ icon: 'error', title: 'Error', text: String(err), confirmButtonText: 'OK' });
        } else if (msg) {
          Swal.fire({ icon, title: icon==='success'?'Success':icon==='warning'?'Warning':'Info', text: String(msg), confirmButtonText: 'OK' });
        }

        const form = document.getElementById('formStatus');
        if (form) {
          form.addEventListener('submit', async function(e){
            e.preventDefault();
            const sel = form.querySelector('#status');
            const to = sel ? sel.options[sel.selectedIndex].textContent.trim() : '';
            const res = await Swal.fire({
              title: 'Change status?',
              text: to ? ('Change customer status to ' + to + '?') : 'Change customer status?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, change',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          });
        }
      } catch(_) {}
    })();
  </script>
</body>
</html>
