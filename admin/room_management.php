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
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? 'update_status';
        if ($action === 'delete') {
            $rid = (int)($_POST['room_id'] ?? 0);
            if ($rid > 0) {
                $stmt = db()->prepare('DELETE FROM rooms WHERE room_id = ?');
                $stmt->bind_param('i', $rid);
                if ($stmt->execute()) {
                    $okmsg = 'Room deleted';
                } else {
                    $error = 'Delete failed';
                }
                $stmt->close();
            } else {
                $error = 'Bad input';
            }
        } else {
            $rid = (int)($_POST['room_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            if ($rid <= 0 || !in_array($new_status, $allowed_status, true)) {
                $error = 'Bad input';
            } else {
                $stmt = db()->prepare('UPDATE rooms SET status = ? WHERE room_id = ?');
                $stmt->bind_param('si', $new_status, $rid);
                if ($stmt->execute()) {
                    $okmsg = 'Status updated';
                } else {
                    $error = 'Update failed';
                }
                $stmt->close();
            }
        }
    }
}

$filter = $_GET['filter'] ?? 'all';
$where = '';
if (in_array($filter, $allowed_status, true)) {
    $where = ' WHERE r.status = ? ';
}

$sql = 'SELECT r.room_id, r.title, r.room_type, r.status, r.created_at, r.price_per_day, u.name AS owner_name, u.user_id AS owner_id
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id' . $where . ' ORDER BY r.room_id DESC';
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
  <title>Admin Room Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Room Management (Admin)</h1>
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
              <th>Type</th>
              <th>Status</th>
              <th>Price/day</th>
              <th>Created</th>
              <th>View</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['room_id']; ?></td>
                <td><?php echo htmlspecialchars($r['title']); ?></td>
                <td><?php echo htmlspecialchars(($r['owner_name'] ?? 'N/A') . ' (#' . (int)($r['owner_id'] ?? 0) . ')'); ?></td>
                <td><?php echo htmlspecialchars($r['room_type']); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td><?php echo number_format((float)$r['price_per_day'], 2); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="room_view.php?id=<?php echo (int)$r['room_id']; ?>">View</a>
              </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="text-center py-4">No rooms found.</td></tr>
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
