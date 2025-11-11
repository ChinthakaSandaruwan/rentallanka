<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100; $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096); $read = $pos - $step;
        fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
  }
}

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
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       PROPERTY READ - Shared Design System (rl-*)
       =========================== */
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.5rem 2rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.75rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1.1rem 1.5rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1.05rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    /* Property cards */
    .prop-card { position:relative; transition:all .3s cubic-bezier(.4,0,.2,1); }
    .prop-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .25s ease; }
    .prop-card:hover { transform:translateY(-6px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent) !important; }
    .prop-card:hover::before { opacity:1; }
    .prop-title { font-weight:800; color:var(--rl-text); }
    .prop-price { font-weight:800; color:var(--rl-dark); }

    .card-img-top { object-fit:cover; height: 200px; }
    .placeholder-img { background:linear-gradient(135deg,#eef2f7 0%, #e2e8f0 100%); color:#64748b; height:200px; }

    .btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; border-radius:10px; box-shadow:0 4px 16px rgba(0,78,152,.2); }
    .btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-1px); }

    .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

    @media (max-width: 767px){ .rl-page-header{ padding:1.1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } .card-img-top,.placeholder-img{ height:180px; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-houses"></i> Read Properties</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>
  <?php if ($flash): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-body py-3">
        <div class="d-flex align-items-start">
          <i class="bi <?php echo ($flash_type==='success')?'bi-check-circle text-success':'bi-exclamation-triangle text-warning'; ?> me-2"></i>
          <div><?php echo htmlspecialchars($flash); ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <?php if (!empty($db_error)): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-body py-3">
        <div class="d-flex align-items-start">
          <i class="bi bi-info-circle text-primary me-2"></i>
          <div><?php echo htmlspecialchars('Some details could not be loaded due to a database schema difference.'); ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-card-list"></i> Your Properties</h2></div>
    <div class="rl-form-body">
      <?php if (!$props): ?>
        <div class="rl-empty-state">
          <i class="bi bi-house"></i>
          <p class="mb-3">No properties yet.</p>
          <a href="../property/property_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Property</a>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($props as $p): ?>
            <?php $can_edit = (strtotime((string)$p['created_at']) + 24*3600) > time(); ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="rl-form-card h-100 prop-card">
                <?php $img = trim((string)($p['image'] ?? '')); ?>
                <?php if ($img): ?>
                  <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="Property image">
                <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center placeholder-img">
                    <span>No image</span>
                  </div>
                <?php endif; ?>
                <div class="rl-form-body d-flex flex-column">
                  <div class="d-flex align-items-start justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-secondary"><?php echo htmlspecialchars($p['property_code'] ?: ('PROP-' . str_pad((string)$p['property_id'], 6, '0', STR_PAD_LEFT))); ?></span>
                      <?php if (!empty($p['gallery_count'])): ?>
                        <span class="badge bg-light text-dark border">Gallery: <?php echo (int)$p['gallery_count']; ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="badge bg-secondary align-self-start text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span>
                  </div>

                  <h5 class="mt-2 mb-1 prop-title"><?php echo htmlspecialchars($p['title'] ?: 'Untitled'); ?></h5>
                  <div class="mb-2 small prop-price">LKR <?php echo number_format((float)($p['price_per_month'] ?? 0), 2); ?>/mo</div>

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
