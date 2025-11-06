<?php
require_once __DIR__ . '/../../config/config.php';

// Fetch latest available properties with optional location info
$items_props = [];
$sql = 'SELECT p.property_id, p.title, p.description, p.image, p.price_per_month, p.property_type, p.status,
               pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name, l.address, l.postal_code
        FROM properties p
        LEFT JOIN locations l ON l.property_id = p.property_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        WHERE p.status = "available"
        ORDER BY p.property_id DESC
        LIMIT 100';
$res = db()->query($sql);
if ($res) { while ($row = $res->fetch_assoc()) { $items_props[] = $row; } $res->free(); }
$total = count($items_props);

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h1 class="h4 mb-1 d-flex align-items-center"><i class="bi bi-building me-2"></i>All Properties <span class="badge bg-secondary ms-2"><?php echo (int)$total; ?></span></h1>
      <div class="text-muted small">Browse currently available listings</div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo $base_url; ?>/public/includes/advance_search_property.php" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Advanced Search</a>
      <a href="<?php echo $base_url; ?>/" class="btn btn-outline-secondary btn-sm">Home</a>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    <?php foreach ($items_props as $p): ?>
      <div class="col">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($p['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($p['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
            <?php $img = $p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($p['property_type'] ?? '')); ?></div>
            <?php $loc = trim(implode(', ', array_filter([($p['city_name'] ?? ''), ($p['district_name'] ?? ''), ($p['province_name'] ?? '')]))); if ($loc !== ''): ?>
              <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($loc); ?></div>
            <?php endif; ?>
            <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 120, '…')); ?></p>
            <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($p['price_per_month']); ?>/month</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>"><i class="bi bi-eye me-1"></i>View</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items_props): ?>
      <div class="col-12"><div class="alert alert-light border">No properties found.</div></div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
