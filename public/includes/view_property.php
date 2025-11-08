<?php
require_once __DIR__ . '/../../config/config.php';

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch property (only available) with location names
$sql = 'SELECT p.*, u.name AS owner_name, l.address, l.postal_code,
               pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name
        FROM properties p
        LEFT JOIN users u ON u.user_id = p.owner_id
        LEFT JOIN locations l ON l.property_id = p.property_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
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
     
    </div>
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header">Overview</div>
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo htmlspecialchars($prop['title']); ?></h1>
          <dl class="row mb-0">
            <?php if (!empty($prop['property_code'])): ?>
              <dt class="col-sm-4">Code</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['property_code']); ?></dd>
            <?php endif; ?>
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars(ucfirst($prop['property_type'] ?? '')); ?></dd>
            <dt class="col-sm-4">Price / month</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$prop['price_per_month'], 2); ?></dd>
            <?php if (isset($prop['sqft']) && $prop['sqft'] !== null && $prop['sqft'] !== ''): ?>
              <dt class="col-sm-4">Area</dt><dd class="col-sm-8"><?php echo number_format((float)$prop['sqft'], 2); ?> sqft</dd>
            <?php endif; ?>
            <dt class="col-sm-4">Bedrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bedrooms']; ?></dd>
            <dt class="col-sm-4">Bathrooms</dt><dd class="col-sm-8"><?php echo (int)$prop['bathrooms']; ?></dd>
            <dt class="col-sm-4">Living rooms</dt><dd class="col-sm-8"><?php echo (int)$prop['living_rooms']; ?></dd>
            <?php $loc = trim(implode(', ', array_filter([($prop['address'] ?? ''), ($prop['city_name'] ?? ''), ($prop['district_name'] ?? ''), ($prop['province_name'] ?? ''), ($prop['postal_code'] ?? '')]))); if ($loc !== ''): ?>
              <dt class="col-sm-4">Location</dt><dd class="col-sm-8"><?php echo htmlspecialchars($loc); ?></dd>
            <?php endif; ?>
          </dl>
          <div class="mt-3">
            <div class="fw-semibold mb-2">Features</div>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($prop['has_kitchen'])): ?><span class="badge text-bg-secondary">Kitchen</span><?php endif; ?>
              <?php if (!empty($prop['has_parking'])): ?><span class="badge text-bg-secondary">Parking</span><?php endif; ?>
              <?php if (!empty($prop['has_water_supply'])): ?><span class="badge text-bg-secondary">Water</span><?php endif; ?>
              <?php if (!empty($prop['has_electricity_supply'])): ?><span class="badge text-bg-secondary">Electricity</span><?php endif; ?>
              <?php if (!empty($prop['garden'])): ?><span class="badge text-bg-secondary">Garden</span><?php endif; ?>
              <?php if (!empty($prop['gym'])): ?><span class="badge text-bg-secondary">Gym</span><?php endif; ?>
              <?php if (!empty($prop['pool'])): ?><span class="badge text-bg-secondary">Pool</span><?php endif; ?>
              <?php if (empty($prop['has_kitchen']) && empty($prop['has_parking']) && empty($prop['has_water_supply']) && empty($prop['has_electricity_supply']) && empty($prop['garden']) && empty($prop['gym']) && empty($prop['pool'])): ?>
                <span class="text-muted">No extra features listed.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-3">
            <div class="fw-semibold mb-1">Description</div>
            <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($prop['description'] ?? '')); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
