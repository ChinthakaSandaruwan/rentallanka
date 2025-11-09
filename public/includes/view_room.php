<?php
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
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Overview</div>
        <div class="card-body">
          <h1 class="h4 mb-3"><?php echo htmlspecialchars($room['title']); ?></h1>
          <dl class="row mb-0">
            <dt class="col-sm-4">Owner</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['owner_name'] ?? ''); ?></dd>
            <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></dd>
            <dt class="col-sm-4">Beds</dt><dd class="col-sm-8"><?php echo (int)$room['beds']; ?></dd>
            <dt class="col-sm-4">Max Guests</dt><dd class="col-sm-8"><?php echo (int)($room['maximum_guests'] ?? 0); ?></dd>
            <dt class="col-sm-4">Price/Day</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
          </dl>
          <div class="mt-3">
            <div class="fw-semibold mb-1">Description</div>
            <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($room['description'] ?? '')); ?></div>
          </div>
          <?php if ($isOwnerViewing): ?>
            <div class="alert alert-info mt-3" role="alert">
              You are the owner of this room. Renting your own room is disabled.
            </div>
          <?php elseif (strtolower((string)($room['status'] ?? '')) !== 'available'): ?>
            <div class="alert alert-warning mt-3" role="alert">
              This room is not available for rent.
            </div>
          <?php endif; ?>
          <div class="mt-4">
            <?php if (!$isOwnerViewing && strtolower((string)($room['status'] ?? '')) === 'available'): ?>
              <a class="btn btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_room.php?id=<?php echo (int)$rid; ?>">
                <i class="bi bi-bag-check me-1"></i>Rent Now
              </a>
            <?php else: ?>
              <a class="btn btn-primary disabled" href="#" tabindex="-1" aria-disabled="true">
                <i class="bi bi-bag-check me-1"></i>Rent Now
              </a>
            <?php endif; ?>
          </div>
          <div class="mt-4">
            <div class="fw-semibold mb-1">Location</div>
            <?php $locLine = trim(implode(', ', array_filter([$loc['address'], $loc['city'], $loc['district'], $loc['province']]))); ?>
            <div class="text-body-secondary"><?php echo htmlspecialchars($locLine !== '' ? $locLine : 'Not provided'); ?><?php echo $loc['postal_code'] !== '' ? ' • ' . htmlspecialchars($loc['postal_code']) : ''; ?></div>
            <?php if (!empty($loc['google_map_link'])): ?>
              <div class="mt-2">
                <a href="<?php echo htmlspecialchars($loc['google_map_link']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-geo"></i> View on map
                </a>
              </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($meals)): ?>
          <div class="mt-4">
            <div class="fw-semibold mb-1">Meal Options</div>
            <ul class="list-unstyled mb-0">
              <?php foreach ($meals as $m): ?>
                <li>• <?php echo htmlspecialchars(ucwords(str_replace('_',' ', $m['name']))); ?> — LKR <?php echo number_format((float)$m['price'], 2); ?>/day per guest</li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <div class="mt-4">
            <div class="fw-semibold mb-1">Availability</div>
            <div class="d-flex flex-wrap gap-2">
              <?php if (!empty($unavailable)): ?>
                <?php foreach ($unavailable as $rng): ?>
                  <?php $ciLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[0]))); $coLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[1]))); ?>
                  <span class="badge bg-danger"><?php echo $ciLbl; ?> to <?php echo $coLbl; ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="badge bg-success">No current blocks</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="mt-4">
            <div class="fw-semibold mb-1">Pricing Examples</div>
            <?php $ppd = (float)($room['price_per_day'] ?? 0); ?>
            <div class="text-body-secondary small">Prices exclude optional meal costs.</div>
            <ul class="mb-0">
              <li>1 night: LKR <?php echo number_format($ppd * 1, 2); ?></li>
              <li>3 nights: LKR <?php echo number_format($ppd * 3, 2); ?></li>
              <li>7 nights: LKR <?php echo number_format($ppd * 7, 2); ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card mb-3">
        <div class="card-header">Images</div>
        <div class="card-body">
          <?php if ($images): ?>
            <?php $primaryUrl = norm_url($images[0]['image_path'] ?? ''); ?>
            <?php if ($primaryUrl): ?>
              <a href="<?php echo htmlspecialchars($primaryUrl); ?>" target="_blank" download>
                <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="" loading="eager" decoding="async" fetchpriority="high">
              </a>
            <?php endif; ?>
            <?php if (count($images) > 1): ?>
              <div class="row g-2">
                <?php foreach (array_slice($images, 1) as $img): ?>
                  <?php $p = norm_url($img['image_path'] ?? ''); ?>
                  <div class="col-6 col-md-4">
                    <a href="<?php echo htmlspecialchars($p); ?>" target="_blank" download>
                      <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="" loading="lazy" decoding="async">
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>
      </div>
     
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
