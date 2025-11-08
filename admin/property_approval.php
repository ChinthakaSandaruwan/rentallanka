<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
$okmsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['property_id'] ?? 0);
    if ($pid <= 0) {
      $error = 'Bad input';
    } else if ($action === 'approve') {
      // Transition to available without additional package quota checks or deductions
      $owner_id = 0; $cur_status = '';
      $g = db()->prepare('SELECT status, owner_id FROM properties WHERE property_id=?');
      $g->bind_param('i', $pid);
      $g->execute();
      $r = $g->get_result()->fetch_assoc();
      $g->close();
      if ($r) { $cur_status = (string)$r['status']; $owner_id = (int)$r['owner_id']; }

      if ($cur_status === 'available') {
        $okmsg = 'Property is already available.';
      } else {
        $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
        $avail = 'available';
        $st->bind_param('si', $avail, $pid);
        if ($st->execute()) {
          $st->close();
          $okmsg = 'Property approved.';
          if ($owner_id > 0) {
            try {
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
              $title = 'Property approved';
              $msg = 'Your property #' . $pid . ' was approved and is now available.';
              $type = 'system';
              $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore notification failure */ }
          }
        } else { $error = 'Approval failed'; $st->close(); }
      }
    } else if ($action === 'reject') {
      // Set to unavailable and notify owner
      $owner_id = 0;
      $g = db()->prepare('SELECT owner_id FROM properties WHERE property_id=?');
      $g->bind_param('i', $pid);
      $g->execute();
      $row = $g->get_result()->fetch_assoc();
      $g->close();
      if ($row) { $owner_id = (int)$row['owner_id']; }

      $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
      $new_status = 'unavailable';
      $st->bind_param('si', $new_status, $pid);
      if ($st->execute()) {
        $okmsg = 'Property rejected.';
        // Restore one property slot to owner's active paid package (since it was deducted on creation)
        if ($owner_id > 0) {
          try {
            $q = db()->prepare("SELECT bought_package_id FROM bought_packages WHERE user_id=? AND status='active' AND payment_status='paid' AND (end_date IS NULL OR end_date>=NOW()) ORDER BY start_date DESC LIMIT 1");
            $q->bind_param('i', $owner_id);
            $q->execute();
            $bp = $q->get_result()->fetch_assoc();
            $q->close();
            if ($bp && !empty($bp['bought_package_id'])) {
              $bp_id = (int)$bp['bought_package_id'];
              $inc = db()->prepare('UPDATE bought_packages SET remaining_properties = COALESCE(remaining_properties,0) + 1 WHERE bought_package_id = ?');
              $inc->bind_param('i', $bp_id);
              $inc->execute();
              $inc->close();
            }
          } catch (Throwable $e) { /* ignore package restore failure */ }
        }
        if ($owner_id > 0) {
          try {
            $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
            $title = 'Property rejected';
            $msg = 'Your property #' . $pid . ' was rejected by admin.';
            $type = 'system';
            $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid);
            $nt->execute();
            $nt->close();
          } catch (Throwable $e) { /* ignore notification failure */ }
        }
      } else { $error = 'Reject failed'; }
      $st->close();
    }
  }
}

// Fetch pending properties
$rows = [];
$sql = 'SELECT p.*, u.name AS owner_name, u.user_id AS owner_id
        FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id
        WHERE p.status = \'pending\' ORDER BY p.created_at DESC, p.property_id DESC';
$res = db()->query($sql);
while ($res && ($r = $res->fetch_assoc())) { $rows[] = $r; }

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Property Approval</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0">Property Approval</h1>
      <div class="d-flex gap-2">
        <a href="property_management.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-task me-1"></i>Manage</a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
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

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>ID</th>
                <th>Title</th>
                <th>Owner</th>
                <th>Price/mo</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['property_code'] ?? ''); ?></td>
                  <td><?php echo (int)$p['property_id']; ?></td>
                  <td><?php echo htmlspecialchars($p['title']); ?></td>
                  <td><?php echo htmlspecialchars(($p['owner_name'] ?? 'N/A') . ' (#' . (int)($p['owner_id'] ?? 0) . ')'); ?></td>
                  <td><?php echo number_format((float)$p['price_per_month'], 2); ?></td>
                  <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="property_view.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Reject this property?');">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center py-4">No pending properties.</td></tr>
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

