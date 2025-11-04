<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$allowed_status = ['pending','available','unavailable','rented'];
$error = '';
$okmsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $pid = (int)($_POST['property_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } elseif ($pid <= 0 || !in_array($new_status, $allowed_status, true)) {
        $error = 'Bad input';
    } else {
        $stmt = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
        $stmt->bind_param('si', $new_status, $pid);
        if ($stmt->execute()) {
            $okmsg = 'Status updated';
        } else {
            $error = 'Update failed';
        }
        $stmt->close();
    }
}

$filter = $_GET['filter'] ?? 'all';
$where = '';
if (in_array($filter, $allowed_status, true)) {
    $where = ' WHERE p.status = ? ';
}

$sql = 'SELECT p.property_id, p.title, p.status, p.created_at, p.price_per_month, u.username AS owner_name, u.user_id AS owner_id
        FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id' . $where . ' ORDER BY p.property_id DESC';
$stmt = db()->prepare($sql);
if ($where) {
    $stmt->bind_param('s', $filter);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Property Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Property Management (Admin)</h1>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
  <?php if ($okmsg): ?>
    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($okmsg); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
      <label class="form-label mb-0">Filter status</label>
      <select name="filter" class="form-select" onchange="this.form.submit()">
        <option value="all" <?php echo $filter==='all'?'selected':''; ?>>All</option>
        <?php foreach ($allowed_status as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $filter===$s?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript class="col-auto"><button type="submit" class="btn btn-primary">Apply</button></noscript>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Owner</th>
              <th>Status</th>
              <th>Price/mo</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): ?>
              <tr>
                <td><?php echo (int)$p['property_id']; ?></td>
                <td><?php echo htmlspecialchars($p['title']); ?></td>
                <td><?php echo htmlspecialchars(($p['owner_name'] ?? 'N/A') . ' (#' . (int)($p['owner_id'] ?? 0) . ')'); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span></td>
                <td><?php echo number_format((float)$p['price_per_month'], 2); ?></td>
                <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
                    <select name="status" class="form-select form-select-sm" style="max-width: 180px;">
                      <?php foreach ($allowed_status as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($p['status']===$s)?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-center py-4">No properties found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKvVYl0ZlEFp3rG5GkHA7r4XK6tBT3M" crossorigin="anonymous"></script>
</body>
</html>
