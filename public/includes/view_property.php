<?php
require_once __DIR__ . '/../../config/config.php';

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch property (only available)
$sql = 'SELECT p.* , u.username AS owner_name
        FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id
        WHERE p.property_id = ? AND p.status = "available" LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $pid);
$stmt->execute();
$prop = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$prop) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">Property not found or not available.</div></div></body></html>';
  exit;
}

// Gallery (primary first)
$gallery = [];
$gp = db()->prepare('SELECT image_id, image_path, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id DESC');
$gp->bind_param('i', $pid);
$gp->execute();
$rgp = $gp->get_result();
while ($row = $rgp->fetch_assoc()) { $gallery[] = $row; }
$gp->close();

function norm_url($p) {
  if (!$p) return '';
  if (preg_match('#^https?://#i', $p)) return $p;
  return '/' . ltrim($p, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($prop['title']); ?> - Property</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Overview</div>
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo htmlspecialchars($prop['title']); ?></h1>
          <dl class="row mb-0">
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Price / month</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$prop['price_per_month'], 2); ?></dd>
            <dt class="col-sm-4">Bedrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bedrooms']; ?></dd>
            <dt class="col-sm-4">Bathrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bathrooms']; ?></dd>
            <dt class="col-sm-4">Living rooms</dt><dd class="col-sm-8"><?php echo (int)$prop['living_rooms']; ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['property_type'] ?? ''); ?></dd>
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
        <div class="card-header">Images</div>
        <div class="card-body">
          <?php $primaryUrl = norm_url($prop['image'] ?? ''); ?>
          <?php if ($primaryUrl): ?>
            <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="">
          <?php endif; ?>
          <?php if ($gallery): ?>
            <div class="row g-2">
              <?php foreach ($gallery as $img): ?>
                <?php $p = norm_url($img['image_path'] ?? ''); ?>
                <div class="col-6 col-md-4">
                  <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="">
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$primaryUrl): ?>
            <div class="text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-grid">
        <a class="btn btn-primary" href="#">Rent this property</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
