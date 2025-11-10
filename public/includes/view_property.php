<?php
require_once __DIR__ . '/../../config/config.php';

// Make this page cache-friendly to avoid browser resubmission prompts on refresh/back
if (!headers_sent()) {
  header('Cache-Control: private, max-age=300');
  header('Pragma:'); // clear pragma
  header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
}

$pid = (int)($_GET['id'] ?? 0);
// Safety PRG: if any POST reaches here accidentally, redirect to GET URL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pid > 0) {
  http_response_code(303);
  header('Location: ' . rtrim($base_url,'/') . '/public/includes/view_property.php?id=' . $pid);
  exit;
}
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
        LEFT JOIN property_locations l ON l.property_id = p.property_id
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
  <?php
    $pageUrl = rtrim($base_url,'/') . '/public/includes/view_property.php?id=' . (int)$prop['property_id'];
    $imgPrimary = (string)($prop['image'] ?? '');
    $seo = [
      'title' => $prop['title'] . ' - Property',
      'description' => mb_strimwidth((string)($prop['description'] ?? ''), 0, 160, '…'),
      'url' => $pageUrl,
      'image' => $imgPrimary,
      'type' => 'product'
    ];
    require_once __DIR__ . '/seo_meta.php';
  ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
            <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($prop['title']); ?>" loading="eager" decoding="async" fetchpriority="high">
          <?php endif; ?>
          <?php if ($gallery): ?>
            <div class="row g-2">
              <?php foreach ($gallery as $img): ?>
                <?php $p = norm_url($img['image_path'] ?? ''); ?>
                <div class="col-6 col-md-4">
                  <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($prop['title']); ?>" loading="lazy" decoding="async">
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
<div class="position-fixed top-0 start-0 w-100 h-100" style="pointer-events:none;z-index:-1"></div>
<!-- Rent Modal -->
<div class="modal fade" id="rentPropertyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Rent Property</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="rentPropertyModalBody">
        <div class="p-4 text-center text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  function openRentModal(pid){
    const elBody = document.getElementById('rentPropertyModalBody');
    elBody.innerHTML = '<div class="p-4 text-center text-muted">Loading…</div>';
    const url = '<?php echo rtrim($base_url, '/'); ?>/public/includes/rent_property.php?id=' + encodeURIComponent(pid) + '&ajax=1';
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.text())
      .then(html => { elBody.innerHTML = html; })
      .catch(() => { elBody.innerHTML = '<div class="p-4 text-danger">Failed to load. Please try again.</div>'; });
    const m = new bootstrap.Modal(document.getElementById('rentPropertyModal'));
    m.show();
  }
  // Insert Rent button next to title area if property is available and viewer is not owner
  document.addEventListener('DOMContentLoaded', function(){
    try {
      var available = <?php echo json_encode(strtolower((string)($prop['status'] ?? '')) === 'available'); ?>;
      var isOwner = <?php echo json_encode(((int)($prop['owner_id'] ?? 0)) === (int)($_SESSION['user']['user_id'] ?? 0)); ?>;
      if (available && !isOwner) {
        var container = document.querySelector('.card .card-header')?.parentElement;
        var target = document.querySelector('.col-12.col-lg-5 .card .card-body');
        if (target) {
          var wrap = document.createElement('div');
          wrap.className = 'mt-3';
          wrap.innerHTML = '<button type="button" class="btn btn-primary" id="btnRentProperty"><i class="bi bi-bag-check me-1"></i>Rent Now</button>';
          target.appendChild(wrap);
          document.getElementById('btnRentProperty').addEventListener('click', function(){ openRentModal(<?php echo (int)$prop['property_id']; ?>); });
        }
      }
    } catch(e) {}
  });
})();
</script>
<?php
// JSON-LD structured data
$addressParts = [
  'streetAddress' => (string)($prop['address'] ?? ''),
  'addressLocality' => (string)($prop['city_name'] ?? ''),
  'addressRegion' => (string)($prop['district_name'] ?? ''),
  'postalCode' => (string)($prop['postal_code'] ?? ''),
  'addressCountry' => 'LK'
];
$images = [];
if (!empty($prop['image'])) { $images[] = $prop['image']; }
foreach ($gallery as $g) { if (!empty($g['image_path'])) { $images[] = $g['image_path']; } }
$jsonLd = [
  '@context' => 'https://schema.org',
  '@type' => 'Accommodation',
  'name' => (string)$prop['title'],
  'description' => (string)($prop['description'] ?? ''),
  'url' => $pageUrl,
  'image' => array_values(array_unique($images)),
  'address' => array_filter([
    '@type' => 'PostalAddress',
    'streetAddress' => $addressParts['streetAddress'],
    'addressLocality' => $addressParts['addressLocality'],
    'addressRegion' => $addressParts['addressRegion'],
    'postalCode' => $addressParts['postalCode'],
    'addressCountry' => $addressParts['addressCountry']
  ]),
  'offers' => [
    '@type' => 'Offer',
    'priceCurrency' => 'LKR',
    'price' => (string)((float)($prop['price_per_month'] ?? 0)),
    'availability' => 'https://schema.org/InStock'
  ]
];
?>
<script type="application/ld+json">
<?php echo json_encode($jsonLd, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); ?>
</script>
</body>
</html>
