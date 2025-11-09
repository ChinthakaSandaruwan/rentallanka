<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

$allowed_status = ['pending','available','unavailable','rented'];
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$pv_error = '';
$pv_ok = '';

$pid = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $pv_error = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
      $new_status = $_POST['status'] ?? '';
      if ($pid <= 0 || !in_array($new_status, $allowed_status, true)) {
        $pv_error = 'Bad input';
      } else {
        // Get current status and owner
        $cur = null; $owner_id = 0; $cur_status = '';
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
            $pv_error = 'Owner has no active paid package.';
          } else {
            $bp_id = (int)$bp['bought_package_id']; $rem = (int)$bp['remaining_properties'];
            if ($rem <= 0) {
              $pv_error = 'No remaining property slots in owner\'s package.';
            } else {
              // Update status then decrement
              $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
              $st->bind_param('si', $new_status, $pid);
              if ($st->execute()) {
                $st->close();
                $upd = db()->prepare('UPDATE bought_packages SET remaining_properties = GREATEST(remaining_properties-1,0) WHERE bought_package_id=?');
                $upd->bind_param('i', $bp_id);
                $upd->execute();
                $upd->close();
                $pv_ok = 'Status updated and package quota deducted.';
                if ($owner_id > 0) {
                  $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
                  $title = 'Property status updated';
                  $msg = 'Your property #' . $pid . ' status changed to ' . $new_status;
                  $type = 'system';
                  $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid);
                  $nt->execute();
                  $nt->close();
                }
              } else { $pv_error = 'Update failed'; $st->close(); }
            }
          }
        } else {
          // Non-approval or no transition
          $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
          $st->bind_param('si', $new_status, $pid);
          if ($st->execute()) { $pv_ok = 'Status updated'; if ($owner_id > 0) { $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)'); $title = 'Property status updated'; $msg = 'Your property #' . $pid . ' status changed to ' . $new_status; $type = 'system'; $nt->bind_param('isssi', $owner_id, $title, $msg, $type, $pid); $nt->execute(); $nt->close(); } } else { $pv_error = 'Update failed'; }
          $st->close();
        }
      }
    } elseif ($action === 'delete') {
      if ($pid > 0) {
        $ow = 0; $g = db()->prepare('SELECT owner_id FROM properties WHERE property_id=?'); $g->bind_param('i', $pid); $g->execute(); $row = $g->get_result()->fetch_assoc(); $g->close(); if ($row) { $ow = (int)$row['owner_id']; }
        $st = db()->prepare('DELETE FROM properties WHERE property_id = ?');
        $st->bind_param('i', $pid);
        if ($st->execute() && $st->affected_rows > 0) {
          // Insert notification without violating FK (property_id set to NULL after deletion)
          if ($ow > 0) {
            try {
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
              $title = 'Property deleted';
              $msg = 'Your property #' . $pid . ' was deleted by admin';
              $type = 'system';
              $nt->bind_param('isss', $ow, $title, $msg, $type);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore notification failure */ }
          }
          redirect_with_message('property_view.php', 'Property deleted', 'success');
        } else { $pv_error = 'Delete failed'; }
        $st->close();
      } else { $pv_error = 'Bad input'; }
    }
  }
}

// Support list mode (when no id provided)
$list_mode = ($pid <= 0);

// Fetch either details (when id provided) or list (when no id)
$prop = null;
if (!$list_mode) {
  // Fetch property details
  $sql = 'SELECT p.*, 
                 u.user_id AS owner_id,
                 u.name AS owner_name,
                 u.email   AS owner_email,
                 u.profile_image AS owner_profile_image,
                 u.phone   AS owner_phone,
                 u.role    AS owner_role,
                 u.status  AS owner_status,
                 u.created_at AS owner_created_at
          FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id
          WHERE p.property_id = ? LIMIT 1';
  $stmt = db()->prepare($sql);
  $stmt->bind_param('i', $pid);
  $stmt->execute();
  $prop = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$prop) {
    redirect_with_message('property_view.php', 'Property not found', 'error');
  }
}

// Payment slips not used / table may not exist; skip querying
$slips = [];

// Fetch property images (primary first) when in detail mode; else fetch list
$gallery = [];
if (!$list_mode) {
  $gp = db()->prepare('SELECT image_id, image_path, is_primary, uploaded_at FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id DESC');
  $gp->bind_param('i', $pid);
  $gp->execute();
  $rgp = $gp->get_result();
  while ($row = $rgp->fetch_assoc()) { $gallery[] = $row; }
  $gp->close();
} else {
  $properties = [];
  $q = db()->query('SELECT p.property_id, p.title, p.status, p.property_type, p.price_per_month, p.created_at, u.name AS owner_name FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id ORDER BY p.property_id DESC');
  if ($q) { while ($row = $q->fetch_assoc()) { $properties[] = $row; } }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo !$list_mode ? ('Property #'.(int)$prop['property_id'].' - Details') : 'Properties'; ?></title>
  
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKvVYl0ZlEFp3rG5GkHA7r4XK6tBT3M" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="overflow-x-hidden">
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
  <?php if (!$list_mode): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Property Details</h1>
      <div class="d-flex align-items-center gap-2">
        <form method="post" class="d-flex align-items-center gap-2">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="update_status">
          <select name="status" class="form-select form-select-sm" required>
            <?php foreach ($allowed_status as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($prop['status']??'')===$s?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary" type="submit">Update</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this property?');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
        </form>
        <a class="btn btn-outline-secondary btn-sm" href="property_view.php">Back</a>
      </div>
    </div>
    <?php if ($pv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pv_error); ?></div><?php endif; ?>
    <?php if ($pv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($pv_ok); ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-12 col-lg-7 order-lg-2">
        <div class="card">
          <div class="card-header">Overview</div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">ID</dt><dd class="col-sm-8"><?php echo (int)$prop['property_id']; ?></dd>
              <dt class="col-sm-4">Title</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['title'] ?? ''); ?></dd>
              <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars(($prop['owner_name'] ?? 'N/A') . ' (#' . (int)($prop['owner_id'] ?? 0) . ')'); ?></dd>
              <dt class="col-sm-4">Status</dt><dd class="col-sm-8 text-uppercase"><span class="badge bg-secondary"><?php echo htmlspecialchars($prop['status'] ?? ''); ?></span></dd>
              <dt class="col-sm-4">Price / month</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$prop['price_per_month'], 2); ?></dd>
              <dt class="col-sm-4">Bedrooms</dt><dd class="col-sm-8"><?php echo (int)($prop['bedrooms'] ?? 0); ?></dd>
              <dt class="col-sm-4">Bathrooms</dt><dd class="col-sm-8"><?php echo (int)($prop['bathrooms'] ?? 0); ?></dd>
              <dt class="col-sm-4">Living rooms</dt><dd class="col-sm-8"><?php echo (int)($prop['living_rooms'] ?? 0); ?></dd>
              <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['property_type'] ?? ''); ?></dd>
              <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['created_at'] ?? ''); ?></dd>
              <?php if (isset($prop['price_per_night']) && $prop['price_per_night'] !== null): ?>
                <dt class="col-sm-4">Price / night</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$prop['price_per_night'], 2); ?></dd>
              <?php endif; ?>
              <?php if (array_key_exists('sqft', $prop)): ?>
                <dt class="col-sm-4">Area (sqft)</dt><dd class="col-sm-8"><?php echo ($prop['sqft'] !== null) ? number_format((float)$prop['sqft'], 2) : '—'; ?></dd>
              <?php endif; ?>
              <?php if (array_key_exists('garden', $prop)): ?>
                <dt class="col-sm-4">Garden</dt><dd class="col-sm-8"><?php echo ((int)$prop['garden'] ? 'Yes' : 'No'); ?></dd>
              <?php endif; ?>
              <?php if (array_key_exists('gym', $prop)): ?>
                <dt class="col-sm-4">Gym</dt><dd class="col-sm-8"><?php echo ((int)$prop['gym'] ? 'Yes' : 'No'); ?></dd>
              <?php endif; ?>
              <?php if (array_key_exists('pool', $prop)): ?>
                <dt class="col-sm-4">Pool</dt><dd class="col-sm-8"><?php echo ((int)$prop['pool'] ? 'Yes' : 'No'); ?></dd>
              <?php endif; ?>
              <dt class="col-sm-4">Kitchen</dt><dd class="col-sm-8"><?php echo ((int)($prop['kitchen'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Parking</dt><dd class="col-sm-8"><?php echo ((int)($prop['parking'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Water</dt><dd class="col-sm-8"><?php echo ((int)($prop['water_supply'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Electricity</dt><dd class="col-sm-8"><?php echo ((int)($prop['electricity_supply'] ?? 0) ? 'Yes' : 'No'); ?></dd>
            </dl>
            <div class="mt-3">
              <div class="fw-semibold mb-1">Description</div>
              <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($prop['description'] ?? '')); ?></div>
            </div>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">Owner Details</div>
          <div class="card-body">
            <?php if (!empty($prop['owner_profile_image'])): ?>
              <?php
                $opiTop = (string)$prop['owner_profile_image'];
                if (!preg_match('#^https?://#i', $opiTop)) {
                  $opiTop = rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($opiTop, '/');
                }
              ?>
              <div class="float-end ms-3 mb-3">
                <img src="<?php echo htmlspecialchars($opiTop); ?>" alt="Owner profile" class="rounded-circle border" style="width:96px;height:96px;object-fit:cover;">
              </div>
            <?php endif; ?>
            <dl class="row mb-0">
              <dt class="col-sm-4">Owner ID</dt><dd class="col-sm-8"><?php echo (int)($prop['owner_id'] ?? 0); ?></dd>
              <dt class="col-sm-4">name</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_name'] ?? 'N/A'); ?></dd>
              <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_email'] ?? ''); ?></dd>
              <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_phone'] ?? ''); ?></dd>
              <dt class="col-sm-4">Role</dt><dd class="col-sm-8 text-uppercase"><?php echo htmlspecialchars($prop['owner_role'] ?? ''); ?></dd>
              <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-secondary"><?php echo htmlspecialchars($prop['owner_status'] ?? ''); ?></span></dd>
              <dt class="col-sm-4">User Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_created_at'] ?? ''); ?></dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-5 order-lg-1">
        <div class="card">
          <div class="card-header">Images</div>
          <div class="card-body">
            <?php
              $imgUrl = $prop['image'] ?? '';
              if ($imgUrl && !preg_match('#^https?://#i', $imgUrl) && $imgUrl[0] !== '/') { $imgUrl = '/' . ltrim($imgUrl, '/'); }
            ?>
            <?php if ($imgUrl): ?>
              <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="img-fluid rounded mb-3" alt="">
            <?php endif; ?>
            <?php if ($gallery): ?>
              <div class="row g-2">
                <?php foreach ($gallery as $img): ?>
                  <?php $p = $img['image_path'] ?? ''; if ($p && !preg_match('#^https?://#i', $p) && $p[0] !== '/') { $p = '/' . ltrim($p, '/'); } ?>
                  <div class="col-6 col-md-4">
                    <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                      <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="">
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif (!$imgUrl): ?>
              <div class="text-muted">No images uploaded.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Properties</h1>
      <a class="btn btn-outline-secondary btn-sm" href="../property.php">Admin Property Menu</a>
    </div>
    <?php if ($pv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pv_error); ?></div><?php endif; ?>
    <?php if ($pv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($pv_ok); ?></div><?php endif; ?>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:80px;">ID</th>
                <th>Title</th>
                <th>Owner</th>
                <th>Type</th>
                <th>Status</th>
                <th class="text-end">Price/Month</th>
                <th>Created</th>
                <th style="width:110px;" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($properties)): ?>
                <?php foreach ($properties as $p): ?>
                  <tr>
                    <td>#<?php echo (int)$p['property_id']; ?></td>
                    <td><?php echo htmlspecialchars($p['title'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['owner_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['property_type'] ?? ''); ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($p['status'] ?? ''); ?></span></td>
                    <td class="text-end">LKR <?php echo number_format((float)($p['price_per_month'] ?? 0), 2); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at'] ?? ''); ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-primary" href="property_view.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No properties found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
