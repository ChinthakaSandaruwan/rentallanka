<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('owner');

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$props = [];
$db_error = '';
// Primary query with joins for location and gallery count
$sql = 'SELECT p.property_id,
               p.property_code,
               p.title,
               p.description,
               p.property_type,
               p.price_per_month,
               p.bedrooms,
               p.bathrooms,
               p.living_rooms,
               p.garden,
               p.gym,
               p.pool,
               p.sqft,
               p.kitchen,
               p.parking,
               p.water_supply,
               p.electricity_supply,
               p.status,
               p.created_at,
               p.image,
               (SELECT COUNT(*) FROM property_images WHERE property_id=p.property_id AND COALESCE(is_primary,0)=0) AS gallery_count,
               l.address,
               l.postal_code,
               c.name_en AS city_name,
               d.name_en AS district_name,
               pr.name_en AS province_name
        FROM properties p
        LEFT JOIN property_locations l ON l.property_id = p.property_id
        LEFT JOIN cities c ON c.id = l.city_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        WHERE p.owner_id = ?
        ORDER BY p.property_id DESC';
$stmt = db()->prepare($sql);
if ($stmt) {
  $stmt->bind_param('i', $uid);
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $props[] = $row; }
  } else {
    $db_error = db()->error;
  }
  $stmt->close();
} else {
  $db_error = db()->error;
}

// Fallback: if primary query failed (e.g., schema differences), load minimal fields without joins
if (!$props && $db_error !== '') {
  $fallback_sql = 'SELECT property_id, property_code, title, description, property_type, price_per_month, bedrooms, bathrooms, living_rooms, garden, gym, pool, sqft, kitchen, parking, water_supply, electricity_supply, status, created_at, image FROM properties WHERE owner_id=? ORDER BY property_id DESC';
  $fb = db()->prepare($fallback_sql);
  if ($fb && $fb->bind_param('i', $uid) && $fb->execute()) {
    $res2 = $fb->get_result();
    while ($row = $res2->fetch_assoc()) { $row['gallery_count'] = null; $props[] = $row; }
  }
  if ($fb) { $fb->close(); }
}

[$flash, $flash_type] = get_flash();
// Ignore querystring-based flash to prevent persistent alerts when URL includes ?flash=...
if (isset($_GET['flash'])) { $flash = ''; $flash_type = ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Read Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Read Properties</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?> alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($flash); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (!empty($db_error)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars('Some details could not be loaded due to a database schema difference.'); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">List</div>
    <div class="card-body">
      <?php if (!$props): ?>
        <div class="text-center text-muted py-4">No properties yet.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($props as $p): ?>
            <?php $can_edit = (strtotime((string)$p['created_at']) + 24*3600) > time(); ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card h-100 shadow-sm">
                <?php $img = trim((string)($p['image'] ?? '')); ?>
                <?php if ($img): ?>
                  <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="Property image">
                <?php else: ?>
                  <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                    <span class="text-muted">No image</span>
                  </div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                  <div class="d-flex align-items-start justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-secondary"><?php echo htmlspecialchars($p['property_code'] ?: ('PROP-' . str_pad((string)$p['property_id'], 6, '0', STR_PAD_LEFT))); ?></span>
                      <?php if (!empty($p['gallery_count'])): ?>
                        <span class="badge bg-light text-dark border">Gallery: <?php echo (int)$p['gallery_count']; ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="badge bg-secondary align-self-start text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span>
                  </div>

                  <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($p['title'] ?: 'Untitled'); ?></h5>
                  <div class="mb-2 small text-muted">LKR <?php echo number_format((float)($p['price_per_month'] ?? 0), 2); ?>/mo</div>

                  <div class="mb-2">
                    <?php if (!empty($p['property_type'])): ?>
                      <span class="badge bg-info text-dark me-1"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $p['property_type']))); ?></span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark border me-1">Bed: <?php echo (int)($p['bedrooms'] ?? 0); ?></span>
                    <span class="badge bg-light text-dark border me-1">Bath: <?php echo (int)($p['bathrooms'] ?? 0); ?></span>
                    <span class="badge bg-light text-dark border me-1">Living: <?php echo (int)($p['living_rooms'] ?? 0); ?></span>
                    <?php if (!is_null($p['sqft'])): ?>
                      <span class="badge bg-light text-dark border">Sqft: <?php echo (float)$p['sqft']; ?></span>
                    <?php endif; ?>
                  </div>

                  <div class="mb-2">
                    <?php if (!empty($p['kitchen'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-egg-fried"></i> Kitchen</span><?php endif; ?>
                    <?php if (!empty($p['parking'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-p-square"></i> Parking</span><?php endif; ?>
                    <?php if (!empty($p['water_supply'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-droplet"></i> Water</span><?php endif; ?>
                    <?php if (!empty($p['electricity_supply'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-lightning"></i> Electricity</span><?php endif; ?>
                    <?php if (!empty($p['garden'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-flower1"></i> Garden</span><?php endif; ?>
                    <?php if (!empty($p['gym'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-heart-pulse"></i> Gym</span><?php endif; ?>
                    <?php if (!empty($p['pool'])): ?><span class="badge bg-success-subtle border text-success me-1"><i class="bi bi-water"></i> Pool</span><?php endif; ?>
                  </div>

                  <?php
                    $locParts = [];
                    if (!empty($p['city_name'])) $locParts[] = $p['city_name'];
                    if (!empty($p['district_name'])) $locParts[] = $p['district_name'];
                    if (!empty($p['province_name'])) $locParts[] = $p['province_name'];
                    $locLine = implode(', ', array_map('htmlspecialchars', $locParts));
                  ?>
                  <?php if ($locLine || !empty($p['postal_code'])): ?>
                    <div class="mb-2">
                      <i class="bi bi-geo-alt text-muted"></i>
                      <span class="text-muted"><?php echo $locLine; ?><?php echo $locLine && !empty($p['postal_code']) ? ' • ' : ''; ?><?php echo !empty($p['postal_code']) ? htmlspecialchars($p['postal_code']) : ''; ?></span>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($p['address'])): ?>
                    <div class="mb-2 text-muted small"><?php echo htmlspecialchars($p['address']); ?></div>
                  <?php endif; ?>

                  <?php if (!empty($p['description'])): ?>
                    <div class="mt-auto text-truncate" style="-webkit-line-clamp: 3; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;">
                      <?php echo htmlspecialchars($p['description']); ?>
                    </div>
                  <?php endif; ?>
                  <div class="mt-2 small text-muted">
                    <i class="bi bi-calendar-event"></i>
                    Created: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$p['created_at']))); ?>
                    <span class="ms-2">ID: <?php echo (int)$p['property_id']; ?></span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="js/property_read.js" defer></script>
</body>
</html>
