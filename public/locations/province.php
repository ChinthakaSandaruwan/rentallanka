<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ___DIR___ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = ___DIR___ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once ___DIR___ . '/../../config/config.php';
$province_id = (int)($_GET['province_id'] ?? 0);
if ($province_id <= 0) {
  http_response_code(302);
  header('Location: ' . rtrim($base_url,'/') . '/');
  exit;
}

// Province name
$prov = null;
try {
  $st = db()->prepare('SELECT id, name_en FROM provinces WHERE id=? LIMIT 1');
  $st->bind_param('i', $province_id);
  $st->execute();
  $prov = $st->get_result()->fetch_assoc();
  $st->close();
} catch (Throwable $e) {}
if (!$prov) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">Province not found.</div></div></body></html>';
  exit;
}

$province_name = (string)$prov['name_en'];

// Top districts in province
$districts = [];
try {
  $st = db()->prepare('SELECT id, name_en FROM districts WHERE province_id=? ORDER BY name_en LIMIT 12');
  $st->bind_param('i', $province_id);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) { $districts[] = $r; }
  $st->close();
} catch (Throwable $e) {}

// Recent properties in province
$props = [];
try {
  $sql = 'SELECT p.property_id, p.title, p.image, p.price_per_month, p.property_type, p.status,
                 c.name_en AS city_name, d.name_en AS district_name
          FROM properties p
          LEFT JOIN property_locations l ON l.property_id = p.property_id
          LEFT JOIN districts d ON d.id = l.district_id
          LEFT JOIN cities c ON c.id = l.city_id
          WHERE p.status = "available" AND l.province_id = ?
          ORDER BY p.property_id DESC LIMIT 12';
  $st = db()->prepare($sql);
  $st->bind_param('i', $province_id);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) { $props[] = $r; }
  $st->close();
} catch (Throwable $e) {}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$url = rtrim($base_url,'/') . '/public/locations/province.php?province_id=' . $province_id;
$desc = 'Find rental properties and rooms in ' . $province_name . ', Sri Lanka. Browse listings and filter by location.';
$seo = [
  'title' => $province_name . ' Rentals – Properties & Rooms',
  'description' => $desc,
  'url' => $url,
  'type' => 'website'
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require_once ___DIR___ . '/../includes/seo_meta.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once ___DIR___ . '/../includes/navbar.php'; ?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 mb-1">Rentals in <?= h($province_name) ?></h1>
    <p class="text-muted">Browse properties and rooms for rent in <?= h($province_name) ?>. Use advanced search to refine by price, type, and amenities.</p>
    <a class="btn btn-primary btn-sm" href="<?= rtrim($base_url,'/') ?>/public/includes/advance_search.php?type=property&province_id=<?= (int)$province_id ?>">
      <i class="bi bi-funnel me-1"></i>Search Properties in <?= h($province_name) ?></a>
    <a class="btn btn-outline-primary btn-sm" href="<?= rtrim($base_url,'/') ?>/public/includes/advance_search.php?type=room&province_id=<?= (int)$province_id ?>">
      <i class="bi bi-funnel me-1"></i>Search Rooms in <?= h($province_name) ?></a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">Recent Properties in <?= h($province_name) ?></div>
        <div class="card-body">
          <div class="row row-cols-1 row-cols-sm-2 g-3">
            <?php foreach ($props as $p): ?>
              <div class="col">
                <div class="card h-100">
                  <?php if (!empty($p['image'])): $img=$p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
                    <div class="ratio ratio-16x9">
                      <img src="<?= h($img) ?>" class="w-100 h-100 object-fit-cover" alt="<?= h($p['title']) ?>" loading="lazy" decoding="async">
                    </div>
                  <?php endif; ?>
                  <div class="card-body">
                    <h6 class="card-title mb-1"><?= h($p['title']) ?></h6>
                    <div class="text-muted small mb-1"><?= h(ucfirst($p['property_type'] ?? '')) ?> • <?= h($p['district_name'] ?? '') ?></div>
                    <div class="fw-semibold">LKR <?= number_format((float)$p['price_per_month'], 2) ?>/month</div>
                    <a class="stretched-link" href="<?= rtrim($base_url,'/') ?>/public/includes/view_property.php?id=<?= (int)$p['property_id'] ?>"></a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$props): ?>
              <div class="col-12 text-muted">No properties found.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">Explore Districts</div>
        <div class="list-group list-group-flush">
          <?php foreach ($districts as $d): ?>
            <a class="list-group-item list-group-item-action" href="<?= rtrim($base_url,'/') ?>/public/includes/advance_search.php?type=property&province_id=<?= (int)$province_id ?>&district_id=<?= (int)$d['id'] ?>">
              <?= h($d['name_en']) ?>
            </a>
          <?php endforeach; ?>
          <?php if (!$districts): ?>
            <div class="list-group-item text-muted">No districts found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
