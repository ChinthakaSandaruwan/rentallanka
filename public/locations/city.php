<?php
require_once __DIR__ . '/../../config/config.php';
$city_id = (int)($_GET['city_id'] ?? 0);
if ($city_id <= 0) {
  http_response_code(302);
  header('Location: ' . rtrim($base_url,'/') . '/');
  exit;
}

// City and province names
$city = null; $province = null; $district = null;
try {
  $st = db()->prepare('SELECT c.id, c.name_en AS city_name, d.id AS district_id, d.name_en AS district_name, p.id AS province_id, p.name_en AS province_name
                       FROM cities c
                       LEFT JOIN districts d ON d.id = c.district_id
                       LEFT JOIN provinces p ON p.id = d.province_id
                       WHERE c.id=? LIMIT 1');
  $st->bind_param('i', $city_id);
  $st->execute();
  $city = $st->get_result()->fetch_assoc();
  $st->close();
} catch (Throwable $e) {}
if (!$city) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">City not found.</div></div></body></html>';
  exit;
}

$city_name = (string)$city['city_name'];
$province_id = (int)($city['province_id'] ?? 0);
$province_name = (string)($city['province_name'] ?? '');
$district_name = (string)($city['district_name'] ?? '');

// Recent properties in city
$props = [];
try {
  $sql = 'SELECT p.property_id, p.title, p.image, p.price_per_month, p.property_type, p.status
          FROM properties p
          LEFT JOIN locations l ON l.property_id = p.property_id
          WHERE p.status = "available" AND l.city_id = ?
          ORDER BY p.property_id DESC LIMIT 12';
  $st = db()->prepare($sql);
  $st->bind_param('i', $city_id);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) { $props[] = $r; }
  $st->close();
} catch (Throwable $e) {}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$url = rtrim($base_url,'/') . '/public/locations/city.php?city_id=' . $city_id;
$desc = 'Find rental properties and rooms in ' . $city_name . ', ' . $province_name . ', Sri Lanka.';
$seo = [
  'title' => $city_name . ' Rentals – Properties & Rooms',
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
  <?php require_once __DIR__ . '/../includes/seo_meta.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 mb-1">Rentals in <?= h($city_name) ?><?= $province_name !== '' ? ', ' . h($province_name) : '' ?></h1>
    <p class="text-muted">Discover properties for rent in <?= h($city_name) ?>. Filter by price, type and more using Advanced Search.</p>
    <a class="btn btn-primary btn-sm" href="<?= rtrim($base_url,'/') ?>/public/includes/advance_search.php?type=property&city_id=<?= (int)$city_id ?>">
      <i class="bi bi-funnel me-1"></i>Search Properties in <?= h($city_name) ?></a>
    <a class="btn btn-outline-primary btn-sm" href="<?= rtrim($base_url,'/') ?>/public/includes/advance_search.php?type=room&city_id=<?= (int)$city_id ?>">
      <i class="bi bi-funnel me-1"></i>Search Rooms in <?= h($city_name) ?></a>
  </div>

  <div class="card">
    <div class="card-header">Recent Properties in <?= h($city_name) ?></div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
        <?php foreach ($props as $p): ?>
          <div class="col">
            <div class="card h-100">
              <?php if (!empty($p['image'])): $img=$p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
                <div class="ratio ratio-16x9">
                  <?php $webp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $img); ?>
                  <picture>
                    <source type="image/webp" srcset="<?= h($webp) ?>">
                    <img src="<?= h($img) ?>" class="w-100 h-100 object-fit-cover" alt="<?= h($p['title']) ?>" loading="lazy" decoding="async">
                  </picture>
                </div>
              <?php endif; ?>
              <div class="card-body">
                <h6 class="card-title mb-1"><?= h($p['title']) ?></h6>
                <div class="text-muted small mb-1"><?= h(ucfirst($p['property_type'] ?? '')) ?></div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
