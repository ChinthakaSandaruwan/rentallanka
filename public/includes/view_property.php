<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       VIEW PROPERTY PAGE CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-shadow-lg: 0 10px 40px rgba(0,0,0,.15);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
    }
    
    /* Cards */
    .rl-card {
      border: 1px solid var(--rl-border);
      border-radius: var(--rl-radius-lg);
      background: var(--rl-white);
      box-shadow: var(--rl-shadow-sm);
      overflow: hidden;
      transition: all 0.3s ease;
    }
    
    .rl-card:hover {
      box-shadow: var(--rl-shadow-md);
    }
    
    .rl-card-header {
      background: linear-gradient(135deg, #f8fafc 0%, var(--rl-white) 100%);
      border-bottom: 2px solid var(--rl-border);
      padding: 1.25rem 1.5rem;
      font-weight: 700;
      font-size: 1.125rem;
      color: var(--rl-text);
    }
    
    .rl-card-body {
      padding: 1.5rem;
    }
    
    /* Title */
    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 2rem);
      font-weight: 800;
      color: var(--rl-text);
      margin-bottom: 1.5rem;
      line-height: 1.2;
    }
    
    /* Description List */
    .rl-dl dt {
      font-weight: 600;
      color: var(--rl-text-secondary);
      font-size: 0.9375rem;
    }
    
    .rl-dl dd {
      color: var(--rl-text);
      font-weight: 500;
      margin-bottom: 0.75rem;
    }
    
    /* Section Headings */
    .rl-section-heading {
      font-weight: 700;
      font-size: 1rem;
      color: var(--rl-text);
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-section-heading::before {
      content: '';
      width: 4px;
      height: 1.25rem;
      background: linear-gradient(180deg, var(--rl-dark), var(--rl-accent));
      border-radius: 2px;
    }
    
    /* Price Highlight */
    .rl-price-highlight {
      color: var(--rl-dark);
      font-weight: 800;
      font-size: 1.25rem;
    }
    
    /* Buttons */
    .rl-btn {
      border-radius: 10px;
      font-weight: 700;
      padding: 0.75rem 1.75rem;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border: none;
      font-size: 1rem;
      letter-spacing: 0.02em;
    }
    
    .rl-btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.25);
    }
    
    .rl-btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: var(--rl-white);
    }
    
    .rl-btn-primary:active {
      transform: translateY(0);
    }
    
    /* Feature Badges */
    .rl-feature-badge {
      background: linear-gradient(135deg, var(--rl-light-bg) 0%, #f1f1f1 100%);
      border: 1px solid var(--rl-border);
      border-radius: 8px;
      padding: 0.5rem 0.875rem;
      font-weight: 600;
      font-size: 0.875rem;
      color: var(--rl-text-secondary);
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      transition: all 0.2s ease;
    }
    
    .rl-feature-badge:hover {
      background: var(--rl-white);
      border-color: var(--rl-accent);
      color: var(--rl-accent);
      transform: translateY(-1px);
      box-shadow: var(--rl-shadow-sm);
    }
    
    .rl-feature-badge::before {
      content: '✓';
      display: inline-block;
      width: 16px;
      height: 16px;
      background: var(--rl-accent);
      color: var(--rl-white);
      border-radius: 50%;
      font-size: 10px;
      font-weight: 900;
      text-align: center;
      line-height: 16px;
    }
    
    /* Images */
    .rl-image-primary {
      border-radius: var(--rl-radius);
      overflow: hidden;
      box-shadow: var(--rl-shadow-md);
      transition: all 0.3s ease;
      display: block;
      margin-bottom: 1.5rem;
    }
    
    .rl-image-primary:hover {
      box-shadow: var(--rl-shadow-lg);
      transform: translateY(-2px);
    }
    
    .rl-image-primary img {
      width: 100%;
      height: auto;
      display: block;
      border-radius: var(--rl-radius);
    }
    
    .rl-image-thumb {
      border-radius: 8px;
      overflow: hidden;
      box-shadow: var(--rl-shadow-sm);
      transition: all 0.2s ease;
      display: block;
    }
    
    .rl-image-thumb:hover {
      box-shadow: var(--rl-shadow-md);
      transform: scale(1.02);
    }
    
    .rl-image-thumb img {
      width: 100%;
      height: auto;
      display: block;
      border-radius: 8px;
    }
    
    /* Text Utilities */
    .rl-text-muted {
      color: var(--rl-text-muted);
    }
    
    .rl-text-secondary {
      color: var(--rl-text-secondary);
    }
    
    /* Modal */
    .modal-content {
      border-radius: var(--rl-radius-lg);
      border: none;
      box-shadow: var(--rl-shadow-lg);
    }
    
    .modal-header {
      background: linear-gradient(135deg, #f8fafc 0%, var(--rl-white) 100%);
      border-bottom: 2px solid var(--rl-border);
      padding: 1.5rem;
    }
    
    .modal-title {
      font-weight: 800;
      color: var(--rl-text);
      font-size: 1.375rem;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    /* Responsive */
    @media (max-width: 991px) {
      .rl-card-body {
        padding: 1.25rem;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
      }
      
      .rl-btn {
        width: 100%;
        justify-content: center;
      }
    }
    
    @media (max-width: 767px) {
      .rl-container {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
      }
      
      .rl-card-header {
        padding: 1rem 1.25rem;
        font-size: 1rem;
      }
      
      .rl-card-body {
        padding: 1rem;
      }
      
      .rl-page-title {
        font-size: 1.375rem;
      }
      
      .rl-btn {
        font-size: 0.9375rem;
        padding: 0.625rem 1.5rem;
      }
    }
    
    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .rl-card {
      animation: fadeInUp 0.5s ease-out;
    }
    
    .rl-card:nth-child(2) {
      animation-delay: 0.1s;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container rl-container">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card rl-card mb-3">
        <div class="card-header rl-card-header">Images</div>
        <div class="card-body rl-card-body">
          <?php $primaryUrl = norm_url($prop['image'] ?? ''); ?>
          <?php if ($primaryUrl): ?>
            <div class="rl-image-primary">
              <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($prop['title']); ?>" loading="eager" decoding="async" fetchpriority="high">
            </div>
          <?php endif; ?>
          <?php if ($gallery): ?>
            <div class="row g-3">
              <?php foreach ($gallery as $img): ?>
                <?php $p = norm_url($img['image_path'] ?? ''); ?>
                <div class="col-6 col-md-4">
                  <a href="<?php echo htmlspecialchars($p); ?>" target="_blank" class="rl-image-thumb">
                    <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($prop['title']); ?>" loading="lazy" decoding="async">
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$primaryUrl): ?>
            <div class="rl-text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>
      </div>
     
    </div>
    <div class="col-12 col-lg-5">
      <div class="card rl-card">
        <div class="card-header rl-card-header">Overview</div>
        <div class="card-body rl-card-body">
          <h1 class="rl-page-title"><?php echo htmlspecialchars($prop['title']); ?></h1>
          <dl class="row rl-dl mb-0">
            <?php if (!empty($prop['property_code'])): ?>
              <dt class="col-sm-4">Code</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['property_code']); ?></dd>
            <?php endif; ?>
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prop['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars(ucfirst($prop['property_type'] ?? '')); ?></dd>
            <dt class="col-sm-4">Price / month</dt><dd class="col-sm-8 rl-price-highlight">LKR <?php echo number_format((float)$prop['price_per_month'], 2); ?></dd>
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
          <div class="mt-4">
            <div class="rl-section-heading">Features</div>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($prop['has_kitchen'])): ?><span class="rl-feature-badge">Kitchen</span><?php endif; ?>
              <?php if (!empty($prop['has_parking'])): ?><span class="rl-feature-badge">Parking</span><?php endif; ?>
              <?php if (!empty($prop['has_water_supply'])): ?><span class="rl-feature-badge">Water</span><?php endif; ?>
              <?php if (!empty($prop['has_electricity_supply'])): ?><span class="rl-feature-badge">Electricity</span><?php endif; ?>
              <?php if (!empty($prop['garden'])): ?><span class="rl-feature-badge">Garden</span><?php endif; ?>
              <?php if (!empty($prop['gym'])): ?><span class="rl-feature-badge">Gym</span><?php endif; ?>
              <?php if (!empty($prop['pool'])): ?><span class="rl-feature-badge">Pool</span><?php endif; ?>
              <?php if (empty($prop['has_kitchen']) && empty($prop['has_parking']) && empty($prop['has_water_supply']) && empty($prop['has_electricity_supply']) && empty($prop['garden']) && empty($prop['gym']) && empty($prop['pool'])): ?>
                <span class="rl-text-muted">No extra features listed.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-4">
            <div class="rl-section-heading">Description</div>
            <div class="rl-text-secondary"><?php echo nl2br(htmlspecialchars($prop['description'] ?? '')); ?></div>
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
          wrap.innerHTML = '<button type="button" class="btn rl-btn rl-btn-primary" id="btnRentProperty"><i class="bi bi-bag-check"></i>Rent Now</button>';
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
