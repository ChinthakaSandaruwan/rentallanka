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
            $pid = (int)($_POST['property_id'] ?? 0);
            if ($pid > 0) {
                $ow = 0; $g = db()->prepare('SELECT owner_id FROM properties WHERE property_id=?'); $g->bind_param('i', $pid); $g->execute(); $row = $g->get_result()->fetch_assoc(); $g->close(); if ($row) { $ow = (int)$row['owner_id']; }
                $stmt = db()->prepare('DELETE FROM properties WHERE property_id = ?');
                $stmt->bind_param('i', $pid);
                if ($stmt->execute()) {
                    $okmsg = 'Property deleted';
                    if ($ow > 0) { $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)'); $title = 'Property deleted'; $msg = 'Your property #' . $pid . ' was deleted by admin'; $type = 'system'; $nt->bind_param('isssi', $ow, $title, $msg, $type, $pid); $nt->execute(); $nt->close(); }
                } else {
                    $error = 'Delete failed';
                }
                $stmt->close();
            } else {
                $error = 'Bad input';
            }
        } elseif ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $price_per_month = (float)($_POST['price_per_month'] ?? 0);
            $status_in = $_POST['status'] ?? 'pending';
            if (!in_array($status_in, $allowed_status, true)) { $status_in = 'pending'; }
            if ($title !== '' && $price_per_month >= 0) {
                $stmt = db()->prepare('INSERT INTO properties (owner_id, title, description, price_per_month, bedrooms, bathrooms, living_rooms, has_kitchen, has_parking, has_water_supply, has_electricity_supply, property_type, status) VALUES (NULL, ?, NULL, ?, 0, 0, 0, 0, 0, 0, 0, "other", ?)');
                $stmt->bind_param('sds', $title, $price_per_month, $status_in);
                if ($stmt->execute()) {
                    $okmsg = 'Property created';
                } else {
                    $error = 'Create failed';
                }
                $stmt->close();
            } else {
                $error = 'Bad input';
            }
        } else {
            $pid = (int)($_POST['property_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            if ($pid <= 0 || !in_array($new_status, $allowed_status, true)) {
                $error = 'Bad input';
            } else {
                // Fetch current status and owner
                $owner_id = 0; $cur_status = '';
                $g = db()->prepare('SELECT status, owner_id FROM properties WHERE property_id=?');
                $g->bind_param('i', $pid);
                $g->execute();
                $r = $g->get_result()->fetch_assoc();
                $g->close();
                if ($r) { $cur_status = (string)$r['status']; $owner_id = (int)$r['owner_id']; }

                if ($new_status === 'available' && $cur_status !== 'available') {
                    // Require active paid package with remaining_properties > 0
                    $bp = null; $bp_id = 0; $rem = 0;
                    $q = db()->prepare("SELECT bought_package_id, remaining_properties FROM bought_packages WHERE user_id=? AND status='active' AND payment_status='paid' AND (end_date IS NULL OR end_date>=NOW()) ORDER BY start_date DESC LIMIT 1");
                    $q->bind_param('i', $owner_id);
                    $q->execute();
                    $bp = $q->get_result()->fetch_assoc();
                    $q->close();
                    if (!$bp) {
                        $error = 'Owner has no active paid package.';
                    } else {
                        $bp_id = (int)$bp['bought_package_id']; $rem = (int)$bp['remaining_properties'];
                        if ($rem <= 0) {
                            $error = 'No remaining property slots in owner\'s package.';
                        } else {
                            // Update then decrement
                            $stmt = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
                            $stmt->bind_param('si', $new_status, $pid);
                            if ($stmt->execute()) {
                                $stmt->close();
                                $upd = db()->prepare('UPDATE bought_packages SET remaining_properties = GREATEST(remaining_properties-1,0) WHERE bought_package_id=?');
                                $upd->bind_param('i', $bp_id);
                                $upd->execute();
                                $upd->close();
                                $okmsg = 'Status updated and package quota deducted.';
                                if ($owner_id > 0) {
                                    $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
                                    $title = 'Property status updated';
                                    $msg = 'Your property #' . $pid . ' status changed to ' . $new_status;
                                    $type = 'system';
                                    $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid);
                                    $nt->execute();
                                    $nt->close();
                                }
                            } else {
                                $error = 'Update failed';
                                $stmt->close();
                            }
                        }
                    }
                } else {
                    // Other transitions without quota logic
                    $stmt = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
                    $stmt->bind_param('si', $new_status, $pid);
                    if ($stmt->execute()) {
                        $okmsg = 'Status updated';
                        if ($owner_id > 0) {
                            $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
                            $title = 'Property status updated';
                            $msg = 'Your property #' . $pid . ' status changed to ' . $new_status;
                            $type = 'system';
                            $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid);
                            $nt->execute();
                            $nt->close();
                        }
                    } else { $error = 'Update failed'; }
                    $stmt->close();
                }
            }
        }
    }
}

$filter = $_GET['filter'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$where = '';
if (in_array($filter, $allowed_status, true)) {
    $where = ' WHERE p.status = ? ';
}
if ($q !== '') {
    $cond = ' (p.title LIKE CONCAT("%", ?, "%") OR u.name LIKE CONCAT("%", ?, "%") OR p.property_id = ?) ';
    $where .= ($where ? ' AND ' : ' WHERE ') . $cond;
}

$sql = 'SELECT p.*, u.name AS owner_name, u.user_id AS owner_id
        FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id' . $where . ' ORDER BY p.property_id DESC';
$stmt = db()->prepare($sql);
if ($where) {
    if (in_array($filter, $allowed_status, true) && $q !== '') {
        $qid = (int)$q;
        $stmt->bind_param('sssi', $filter, $q, $q, $qid);
    } elseif (in_array($filter, $allowed_status, true)) {
        $stmt->bind_param('s', $filter);
    } else {
        $qid = (int)$q;
        $stmt->bind_param('ssi', $q, $q, $qid);
    }
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Property Management (Admin)</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
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

  <form method="get" class="row g-2 align-items-end mb-3 needs-validation" novalidate>
    <div class="col-12 col-md-4 col-lg-3">
      <label for="filter" class="form-label mb-0">Filter status</label>
      <select id="filter" name="filter" class="form-select" onchange="this.form.submit()" required>
        <option value="all" <?php echo $filter==='all'?'selected':''; ?>>All</option>
        <?php foreach ($allowed_status as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $filter===$s?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Please choose a filter.</div>
    </div>
    <div class="col-12 col-md">
      <label for="q" class="form-label mb-0">Search</label>
      <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Title, owner, or ID" aria-label="Search by title, owner, or ID" maxlength="120">
    </div>
    <div class="col-12 col-md-auto d-grid">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </form>

  

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
              <th>Status</th>
              <th>Price/mo</th>
              <th>Created</th>
              <th>View</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): ?>
              <tr>
                <td><?php echo htmlspecialchars($p['property_code'] ?? ''); ?></td>
                <td><?php echo (int)$p['property_id']; ?></td>
                <td><?php echo htmlspecialchars($p['title']); ?></td>
                <td><?php echo htmlspecialchars(($p['owner_name'] ?? 'N/A') . ' (#' . (int)($p['owner_id'] ?? 0) . ')'); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span></td>
                <td><?php echo number_format((float)$p['price_per_month'], 2); ?></td>
                <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-secondary" href="property_view.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="text-center py-4">No properties found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>
</body>
</html>
