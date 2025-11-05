<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
  redirect_with_message('property_management.php', 'Invalid property ID', 'error');
}

// Fetch property details
$sql = 'SELECT p.*, u.username AS owner_name, u.user_id AS owner_id
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Property Details</h1>
      <a class="btn btn-outline-secondary btn-sm" href="property_management.php">Back</a>
    </div>

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
            </dl>
            <div class="mt-3">
              <div class="fw-semibold mb-1">Description</div>
              <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($prop['description'] ?? '')); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="card mb-3">
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
        <div class="card">
          <div class="card-header">Payment Slips</div>
          <div class="list-group list-group-flush">
            <?php foreach ($slips as $s): ?>
              <?php $spth = $s['slip_path'] ?? ''; if ($spth && !preg_match('#^https?://#i', $spth) && $spth[0] !== '/') { $spth = '/' . ltrim($spth, '/'); } ?>
              <a class="list-group-item list-group-item-action" target="_blank" href="<?php echo htmlspecialchars($spth); ?>">
                #<?php echo (int)$s['slip_id']; ?> 
                <span class="text-muted">(<?php echo htmlspecialchars($s['uploaded_at']); ?>)</span>
              </a>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
              <div class="list-group-item text-muted">No slips attached.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
