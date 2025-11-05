<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
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
        $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
        $st->bind_param('si', $new_status, $pid);
        if ($st->execute()) { $pv_ok = 'Status updated'; } else { $pv_error = 'Update failed'; }
        $st->close();
      }
    } elseif ($action === 'delete') {
      if ($pid > 0) {
        $st = db()->prepare('DELETE FROM properties WHERE property_id = ?');
        $st->bind_param('i', $pid);
        if ($st->execute() && $st->affected_rows > 0) {
          redirect_with_message('property_management.php', 'Property deleted', 'success');
        } else { $pv_error = 'Delete failed'; }
        $st->close();
      } else { $pv_error = 'Bad input'; }
    }
  }
}

if ($pid <= 0) {
  redirect_with_message('property_management.php', 'Invalid property ID', 'error');
}

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
  redirect_with_message('property_management.php', 'Property not found', 'error');
}

// Fetch payment slips
$slips = [];
$sp = db()->prepare('SELECT slip_id, slip_path, uploaded_at FROM property_payment_slips WHERE property_id = ? ORDER BY slip_id DESC');
$sp->bind_param('i', $pid);
$sp->execute();
$rsp = $sp->get_result();
while ($row = $rsp->fetch_assoc()) { $slips[] = $row; }
$sp->close();

// Fetch property images (primary first)
$gallery = [];
$gp = db()->prepare('SELECT image_id, image_path, is_primary, uploaded_at FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id DESC');
$gp->bind_param('i', $pid);
$gp->execute();
$rgp = $gp->get_result();
while ($row = $rgp->fetch_assoc()) { $gallery[] = $row; }
$gp->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Property #<?php echo (int)$prop['property_id']; ?> - Details</title>
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKvVYl0ZlEFp3rG5GkHA7r4XK6tBT3M" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="overflow-x-hidden">
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
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
        <a class="btn btn-outline-secondary btn-sm" href="property_management.php">Back</a>
      </div>
    </div>
    <?php if ($pv_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pv_error); ?></div><?php endif; ?>
    <?php if ($pv_ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($pv_ok); ?></div><?php endif; ?>

    <div class="row g-4">
      <div class="col-12 col-lg-7">
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
              <dt class="col-sm-4">Kitchen</dt><dd class="col-sm-8"><?php echo ((int)($prop['has_kitchen'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Parking</dt><dd class="col-sm-8"><?php echo ((int)($prop['has_parking'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Water</dt><dd class="col-sm-8"><?php echo ((int)($prop['has_water_supply'] ?? 0) ? 'Yes' : 'No'); ?></dd>
              <dt class="col-sm-4">Electricity</dt><dd class="col-sm-8"><?php echo ((int)($prop['has_electricity_supply'] ?? 0) ? 'Yes' : 'No'); ?></dd>
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
      <div class="col-12 col-lg-5">
        <div class="card mb-3">
          <div class="card-header" id="slips">Payment Slips</div>
          <div class="list-group list-group-flush">
            <?php foreach ($slips as $s): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  #<?php echo (int)$s['slip_id']; ?>
                  <span class="text-muted">(<?php echo htmlspecialchars($s['uploaded_at']); ?>)</span>
                </div>
                <a class="btn btn-sm btn-outline-primary" target="_blank" href="slip_view.php?slip_id=<?php echo (int)$s['slip_id']; ?>">Open</a>
              </div>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
              <div class="list-group-item text-muted">No slips attached.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-header">Image</div>
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
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
