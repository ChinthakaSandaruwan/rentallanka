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

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch room
$sql = 'SELECT r.*, u.name AS owner_name
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id
        WHERE r.room_id = ? LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $rid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
  http_response_code(404);
  echo '<!doctype html><html><body><div class="container p-4"><div class="alert alert-warning">Room not found or not available.</div></div></body></html>';
  exit;
}

$currentUserId = (int)($_SESSION['user']['user_id'] ?? 0);
$isOwnerViewing = $currentUserId > 0 && ((int)($room['owner_id'] ?? 0) === $currentUserId);

// Gallery (primary first)
$images = [];
$si = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id DESC');
$si->bind_param('i', $rid);
$si->execute();
$rsi = $si->get_result();
while ($row = $rsi->fetch_assoc()) { $images[] = $row; }
$si->close();

// Location
$loc = [
  'province' => '',
  'district' => '',
  'city' => '',
  'address' => '',
  'google_map_link' => '',
  'postal_code' => ''
];
try {
  $ql = db()->prepare('SELECT l.address, l.google_map_link, l.postal_code, p.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name
                        FROM room_locations l
                        LEFT JOIN provinces p ON p.id = l.province_id
                        LEFT JOIN districts d ON d.id = l.district_id
                        LEFT JOIN cities c ON c.id = l.city_id
                        WHERE l.room_id = ? LIMIT 1');
  $ql->bind_param('i', $rid);
  $ql->execute();
  $lr = $ql->get_result()->fetch_assoc();
  $ql->close();
  if ($lr) {
    $loc['province'] = (string)($lr['province_name'] ?? '');
    $loc['district'] = (string)($lr['district_name'] ?? '');
    $loc['city'] = (string)($lr['city_name'] ?? '');
    $loc['address'] = (string)($lr['address'] ?? '');
    $loc['google_map_link'] = (string)($lr['google_map_link'] ?? '');
    $loc['postal_code'] = (string)($lr['postal_code'] ?? '');
  }
} catch (Throwable $e) {}

// Meals
$meals = [];
try {
  $qm = db()->prepare('SELECT meal_id, meal_name, price FROM room_meals WHERE room_id=? ORDER BY meal_name');
  $qm->bind_param('i', $rid);
  $qm->execute();
  $mr = $qm->get_result();
  while ($row = $mr->fetch_assoc()) {
    $meals[] = [
      'id' => (int)$row['meal_id'],
      'name' => (string)$row['meal_name'],
      'price' => max(0.0, (float)($row['price'] ?? 0)),
    ];
  }
  $qm->close();
} catch (Throwable $e) {}

// Unavailable ranges
$unavailable = [];
try {
  $qb = db()->prepare("SELECT DATE(checkin_date) AS ci, DATE(checkout_date) AS co FROM room_rents WHERE room_id=? AND status IN ('booked','checked_in') AND checkout_date > NOW() ORDER BY checkin_date");
  $qb->bind_param('i', $rid);
  $qb->execute();
  $rs = $qb->get_result();
  while ($row = $rs->fetch_assoc()) {
    $ciD = (string)($row['ci'] ?? '');
    $coD = (string)($row['co'] ?? '');
    if ($ciD !== '' && $coD !== '') { $unavailable[] = [$ciD, $coD]; }
  }
  $qb->close();
} catch (Throwable $e) {}

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
  <title><?php echo htmlspecialchars($room['title']); ?> - Room</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       VIEW ROOM PAGE CUSTOM STYLES
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
    
    /* Container */
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
    .rl-dl {
      margin-bottom: 0;
    }
    
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
    
    .rl-dl dd:last-child {
      margin-bottom: 0;
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
    
    .rl-btn-primary:hover:not(:disabled) {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: var(--rl-white);
    }
    
    .rl-btn-primary:active:not(:disabled) {
      transform: translateY(0);
    }
    
    .rl-btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    .rl-btn-outline {
      background: var(--rl-white);
      border: 2px solid var(--rl-primary);
      color: var(--rl-primary);
      padding: 0.5rem 1rem;
      font-size: 0.9375rem;
    }
    
    .rl-btn-outline:hover {
      background: var(--rl-primary);
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    /* Badges */
    .rl-badge {
      border-radius: 8px;
      padding: 0.5rem 0.875rem;
      font-weight: 700;
      font-size: 0.875rem;
      display: inline-block;
    }
    
    .rl-badge-success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      border: 1px solid #6ee7b7;
    }
    
    .rl-badge-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      border: 1px solid #f87171;
    }
    
    /* Alerts */
    .rl-alert {
      border-radius: var(--rl-radius);
      padding: 1rem 1.25rem;
      border-left: 4px solid;
      margin-top: 1rem;
    }
    
    .rl-alert-info {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      border-left-color: var(--rl-primary);
      color: #1e3a8a;
    }
    
    .rl-alert-warning {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border-left-color: #f59e0b;
      color: #92400e;
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
    
    /* List Styling */
    .rl-list {
      list-style: none;
      padding-left: 0;
    }
    
    .rl-list li {
      padding-left: 1.5rem;
      position: relative;
      margin-bottom: 0.5rem;
      color: var(--rl-text-secondary);
    }
    
    .rl-list li::before {
      content: '•';
      position: absolute;
      left: 0.5rem;
      color: var(--rl-dark);
      font-weight: 900;
      font-size: 1.25rem;
    }
    
    /* Pricing Section */
    .rl-pricing-item {
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--rl-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .rl-pricing-item:last-child {
      border-bottom: none;
    }
    
    .rl-pricing-label {
      color: var(--rl-text-secondary);
      font-weight: 500;
    }
    
    .rl-pricing-value {
      color: var(--rl-dark);
      font-weight: 700;
      font-size: 1.125rem;
    }
    
    /* Modal Enhancements */
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
    
    /* Text Utilities */
    .rl-text-muted {
      color: var(--rl-text-muted);
    }
    
    .rl-text-secondary {
      color: var(--rl-text-secondary);
    }
    
    /* Responsive Adjustments */
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
      
      .rl-btn-outline {
        width: auto;
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
    <div class="col-12 col-lg-7 order-lg-2">
      <div class="card rl-card">
        <div class="card-header rl-card-header">Overview</div>
        <div class="card-body rl-card-body">
          <h1 class="rl-page-title"><?php echo htmlspecialchars($room['title']); ?></h1>
          <dl class="row rl-dl">
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></dd>
            <dt class="col-sm-4">Beds</dt><dd class="col-sm-8"><?php echo (int)$room['beds']; ?></dd>
            <dt class="col-sm-4">Max Guests</dt><dd class="col-sm-8"><?php echo (int)($room['maximum_guests'] ?? 0); ?></dd>
            <dt class="col-sm-4">Price Per Night</dt><dd class="col-sm-8 rl-price-highlight">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
          </dl>
          <div class="mt-4">
            <div class="rl-section-heading">Description</div>
            <div class="rl-text-secondary"><?php echo nl2br(htmlspecialchars($room['description'] ?? '')); ?></div>
          </div>
          <?php if ($isOwnerViewing): ?>
            <div class="alert rl-alert rl-alert-info" role="alert">
              <strong>Owner View:</strong> You are the owner of this room. Renting your own room is disabled.
            </div>
          <?php elseif (strtolower((string)($room['status'] ?? '')) !== 'available'): ?>
            <div class="alert rl-alert rl-alert-warning" role="alert">
              <strong>Not Available:</strong> This room is not available for rent at the moment.
            </div>
          <?php endif; ?>
          <div class="mt-4">
            <?php if (!$isOwnerViewing && strtolower((string)($room['status'] ?? '')) === 'available'): ?>
              <button type="button" class="btn rl-btn rl-btn-primary" id="btnRentNow" data-room-id="<?php echo (int)$rid; ?>">
                <i class="bi bi-bag-check"></i>Rent Now
              </button>
            <?php else: ?>
              <button type="button" class="btn rl-btn rl-btn-primary" disabled>
                <i class="bi bi-bag-check"></i>Rent Now
              </button>
            <?php endif; ?>
          </div>
          <div class="mt-4">
            <div class="rl-section-heading">Location</div>
            <?php $locLine = trim(implode(', ', array_filter([$loc['address'], $loc['city'], $loc['district'], $loc['province']]))); ?>
            <div class="rl-text-secondary"><?php echo htmlspecialchars($locLine !== '' ? $locLine : 'Not provided'); ?><?php echo $loc['postal_code'] !== '' ? ' • ' . htmlspecialchars($loc['postal_code']) : ''; ?></div>
            <?php if (!empty($loc['google_map_link'])): ?>
              <div class="mt-2">
                <a href="<?php echo htmlspecialchars($loc['google_map_link']); ?>" target="_blank" rel="noopener" class="btn btn-sm rl-btn-outline">
                  <i class="bi bi-geo-alt"></i> View on Map
                </a>
              </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($meals)): ?>
          <div class="mt-4">
            <div class="rl-section-heading">Meal Options</div>
            <ul class="rl-list mb-0">
              <?php foreach ($meals as $m): ?>
                <li><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $m['name']))); ?> — <span class="fw-bold" style="color: var(--rl-dark);">LKR <?php echo number_format((float)$m['price'], 2); ?></span>/day per guest</li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <div class="mt-4">
            <div class="rl-section-heading">Availability</div>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($unavailable)): ?>
                <?php foreach ($unavailable as $rng): ?>
                  <?php $ciLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[0]))); $coLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[1]))); ?>
                  <span class="badge rl-badge rl-badge-danger"><?php echo $ciLbl; ?> to <?php echo $coLbl; ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="badge rl-badge rl-badge-success">No Current Blocks</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="mt-4">
            <div class="rl-section-heading">Pricing Examples</div>
            <?php $ppd = (float)($room['price_per_day'] ?? 0); ?>
            <div class="rl-text-muted small mb-2">Prices exclude optional meal costs.</div>
            <div>
              <div class="rl-pricing-item"><span class="rl-pricing-label">1 night</span><span class="rl-pricing-value">LKR <?php echo number_format($ppd * 1, 2); ?></span></div>
              <div class="rl-pricing-item"><span class="rl-pricing-label">3 nights</span><span class="rl-pricing-value">LKR <?php echo number_format($ppd * 3, 2); ?></span></div>
              <div class="rl-pricing-item"><span class="rl-pricing-label">7 nights</span><span class="rl-pricing-value">LKR <?php echo number_format($ppd * 7, 2); ?></span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5 order-lg-1">
      <div class="card rl-card mb-3">
        <div class="card-header rl-card-header">Images</div>
        <div class="card-body rl-card-body">
          <?php if ($images): ?>
            <?php $primaryUrl = norm_url($images[0]['image_path'] ?? ''); ?>
            <?php if ($primaryUrl): ?>
              <a href="<?php echo htmlspecialchars($primaryUrl); ?>" target="_blank" class="rl-image-primary">
                <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid" alt="Room Image" loading="eager" decoding="async" fetchpriority="high">
              </a>
            <?php endif; ?>
            <?php if (count($images) > 1): ?>
              <div class="row g-3">
                <?php foreach (array_slice($images, 1) as $img): ?>
                  <?php $p = norm_url($img['image_path'] ?? ''); ?>
                  <div class="col-6 col-md-4">
                    <a href="<?php echo htmlspecialchars($p); ?>" target="_blank" class="rl-image-thumb">
                      <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid" alt="Room Image" loading="lazy" decoding="async">
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="rl-text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>
      </div>
     
    </div>
  </div>
</div>
<!-- Rent Modal -->
<div class="modal fade" id="rentModal" tabindex="-1" aria-labelledby="rentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rentModalLabel">Rent Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="rentModalBody">
        <div class="d-flex align-items-center gap-3 py-4 justify-content-center text-muted">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span>Loading…</span>
        </div>
      </div>
    </div>
  </div>
  </div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    const modalEl = document.getElementById('rentModal');
    const modalBody = document.getElementById('rentModalBody');
    let bsModal = null;
    function showSpinner(){
      modalBody.innerHTML = '<div class="d-flex align-items-center gap-3 py-4 justify-content-center text-muted">'
        + '<div class="spinner-border" role="status" aria-hidden="true"></div>'
        + '<span>Loading…</span>'
        + '</div>';
    }

    $(document).on('click', '#btnRentNow', function(e){
      e.preventDefault();
      const id = parseInt(this.getAttribute('data-room-id')||'0',10) || 0;
      if (!id) return;
      if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
      showSpinner();
      bsModal.show();

      $.ajax({
        url: '<?php echo $base_url; ?>/public/includes/rent_room.php',
        method: 'GET',
        data: { id: id, ajax: 1 },
        dataType: 'html',
        success: function(res){
          // If server sent JSON error (e.g., not logged in), try to parse
          try {
            const obj = JSON.parse(res);
            if (obj && obj.status === 'error') {
              modalBody.innerHTML = '<div class="alert alert-danger">' + (obj.message || 'Failed to load form') + '</div>';
              return;
            }
          } catch(_) {}
          modalBody.innerHTML = res;
          // Execute any scripts included in the fragment so live calc/init runs
          const scripts = modalBody.querySelectorAll('script');
          scripts.forEach((old) => {
            const s = document.createElement('script');
            if (old.src) { s.src = old.src; }
            else { s.text = old.textContent || ''; }
            document.body.appendChild(s);
            old.remove();
          });
        },
        error: function(xhr){
          let msg = 'Failed to load form.';
          try { const o = JSON.parse(xhr.responseText||''); if (o && o.message) msg = o.message; } catch(_){ }
          modalBody.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
        }
      });
    });

    // Delegate submit for the form inside modal
    $(document).on('submit', '#rentModal form', function(e){
      e.preventDefault();
      const $form = $(this);
      const data = $form.serializeArray();
      data.push({name: 'ajax', value: '1'});
      const idField = $form.find('input[name="room_id"]').val();
      $.ajax({
        url: '<?php echo $base_url; ?>/public/includes/rent_room.php',
        method: 'POST',
        data: $.param(data),
        dataType: 'json',
        success: function(resp){
          if (resp && resp.status === 'success') {
            modalBody.innerHTML = resp.html || '<div class="alert alert-success">' + (resp.message||'Booked') + '</div>';
            // Optionally refresh parts of page or close after delay
            setTimeout(function(){ if (bsModal) bsModal.hide(); }, 2000);
          } else {
            const msg = (resp && resp.message) ? resp.message : 'Booking failed';
            const host = modalBody.querySelector('#formAlert');
            if (host) {
              host.innerHTML = '<div class="alert alert-danger">' + msg.replace(/\n/g,'<br>') + '</div>';
            } else {
              modalBody.insertAdjacentHTML('afterbegin','<div class="alert alert-danger">' + msg.replace(/\n/g,'<br>') + '</div>');
            }
          }
        },
        error: function(xhr){
          let msg = 'Network error';
          try { const o = JSON.parse(xhr.responseText||''); if (o && o.message) msg = o.message; } catch(_){ }
          const host = modalBody.querySelector('#formAlert');
          if (host) { host.innerHTML = '<div class="alert alert-danger">' + msg + '</div>'; }
          else { modalBody.insertAdjacentHTML('afterbegin','<div class="alert alert-danger">' + msg + '</div>'); }
        }
      });
    });
  })();
</script>
 
</body>
</html>