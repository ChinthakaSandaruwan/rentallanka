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

// Filters
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Inline view selection
$view_id = (int)($_GET['view'] ?? 0);
$view_customer = null;
if ($view_id > 0) {
  $stmtV = db()->prepare("SELECT user_id, name, email, phone, nic, status, profile_image FROM users WHERE user_id = ? AND role='customer' LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('i', $view_id);
    if ($stmtV->execute()) {
      $resV = $stmtV->get_result();
      $view_customer = $resV->fetch_assoc();
      $resV->free();
    }
    $stmtV->close();
  }
}

// Build query
$params = [];
$wheres = ["role='customer'"];
$types = '';
if ($search !== '') {
  $wheres[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR nic LIKE ?)";
  $like = '%' . $search . '%';
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssss';
}
if (in_array($status_filter, ['active','inactive','banned'], true)) {
  $wheres[] = 'status = ?';
  $params[] = $status_filter;
  $types .= 's';
}

$sql = 'SELECT user_id, name, email, phone, nic, status FROM users WHERE ' . implode(' AND ', $wheres) . ' ORDER BY user_id DESC';

$customers = [];
$mysqli = db();
if ($types !== '') {
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) { $customers[] = $row; }
      $res->free();
    }
    $stmt->close();
  }
} else {
  $res = $mysqli->query($sql);
  if ($res) {
    while ($row = $res->fetch_assoc()) { $customers[] = $row; }
    $res->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customers</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Customers</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
      <div class="col-12 col-md-6">
        <label class="form-label" for="q">Search</label>
        <input type="text" id="q" name="q" class="form-control" placeholder="name, email, phone, NIC" value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">Any</option>
          <option value="active" <?php echo $status_filter==='active'?'selected':''; ?>>Active</option>
          <option value="inactive" <?php echo $status_filter==='inactive'?'selected':''; ?>>Inactive</option>
          <option value="banned" <?php echo $status_filter==='banned'?'selected':''; ?>>Banned</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="customer_read.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
      </div>
    </form>

    <?php if ($view_customer): ?>
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Customer Details</span>
        <a class="btn btn-sm btn-outline-secondary" href="customer_read.php">Close</a>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 text-center">
            <?php $img = (string)($view_customer['profile_image'] ?? ''); ?>
            <?php if ($img !== ''): ?>
              <img src="<?php echo $base_url . '/' . ltrim($img, '/'); ?>" alt="Profile" class="rounded-circle border" style="width:96px;height:96px;object-fit:cover;">
            <?php else: ?>
              <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
                <span style="font-weight:600;">No Image</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Name</label>
            <div class="form-control bg-light"><?php echo htmlspecialchars($view_customer['name'] ?? ''); ?></div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">NIC</label>
            <div class="form-control bg-light"><?php echo htmlspecialchars($view_customer['nic'] ?? ''); ?></div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Email</label>
            <div class="form-control bg-light"><?php echo htmlspecialchars($view_customer['email'] ?? ''); ?></div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Phone</label>
            <div class="form-control bg-light"><?php echo htmlspecialchars($view_customer['phone'] ?? ''); ?></div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Status</label>
            <div>
              <span class="badge bg-<?php echo ($view_customer['status']==='active'?'success':($view_customer['status']==='inactive'?'secondary':'danger')); ?>">
                <?php echo htmlspecialchars($view_customer['status'] ?? ''); ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">Customer List</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>NIC</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 120px;">View</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($customers)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr>
              <?php else: ?>
                <?php foreach ($customers as $c): ?>
                  <tr>
                    <td><?php echo (int)$c['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($c['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($c['phone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($c['nic'] ?? ''); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $c['status']==='active'?'success':($c['status']==='inactive'?'secondary':'danger'); ?>">
                        <?php echo htmlspecialchars($c['status']); ?>
                      </span>
                    </td>
                    <td>
                      <a class="btn btn-sm btn-outline-secondary" href="customer_read.php?view=<?php echo (int)$c['user_id']; ?>">
                        <i class="bi bi-eye me-1"></i>View
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
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
