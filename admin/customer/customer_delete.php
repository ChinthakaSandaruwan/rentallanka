<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $flash = 'Invalid request';
    $flash_type = 'error';
  } else {
    $role = 'customer';
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
      $flash = 'Invalid customer id';
      $flash_type = 'error';
    } else {
      $stmt = db()->prepare('DELETE FROM users WHERE user_id = ? AND role = ?');
      if ($stmt) {
        $stmt->bind_param('is', $user_id, $role);
        if ($stmt->execute()) {
          $flash = 'Customer deleted';
          $flash_type = 'success';
        } else {
          $err = $stmt->error;
          $flash = 'Delete failed' . ($err ? ': ' . $err : '');
          $flash_type = 'error';
        }
        $stmt->close();
      } else {
        $flash = 'Delete failed';
        $flash_type = 'error';
      }
    }
  }
}

// Fetch customers to display
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
  $stmt = db()->prepare($sql);
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
  $result = db()->query($sql);
  if ($result) {
    while ($row = $result->fetch_assoc()) { $customers[] = $row; }
    $result->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Customer</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Delete Customer</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <?php if (!empty($flash)): ?>
      <div class="alert alert-<?php echo $flash_type==='error'?'danger':'success'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

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
              <a href="customer_delete.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
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
                <th style="width: 120px;">Actions</th>
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
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int)$c['user_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="bi bi-trash me-1"></i>Delete
                        </button>
                      </form>
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
