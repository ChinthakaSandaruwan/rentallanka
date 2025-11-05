<?php
require_once __DIR__ . '/../../config/config.php';

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

// Fetch room (only available)
$sql = 'SELECT r.*, u.name AS owner_name
        FROM rooms r LEFT JOIN users u ON u.user_id = r.owner_id
        WHERE r.room_id = ? AND r.status = "available" LIMIT 1';
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

// Gallery (primary first)
$images = [];
$si = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id DESC');
$si->bind_param('i', $rid);
$si->execute();
$rsi = $si->get_result();
while ($row = $rsi->fetch_assoc()) { $images[] = $row; }
$si->close();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <dt class="col-sm-4">Price / day</dt><dd class="col-sm-8">LKR <?php echo number_format((float)$room['price_per_day'], 2); ?></dd>
          </dl>
          <div class="mt-3">
            <div class="fw-semibold mb-1">Description</div>
            <div class="text-body-secondary"><?php echo nl2br(htmlspecialchars($room['description'] ?? '')); ?></div>
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
              <img src="<?php echo htmlspecialchars($primaryUrl); ?>" class="img-fluid rounded mb-3" alt="">
            <?php endif; ?>
            <?php if (count($images) > 1): ?>
              <div class="row g-2">
                <?php foreach (array_slice($images, 1) as $img): ?>
                  <?php $p = norm_url($img['image_path'] ?? ''); ?>
                  <div class="col-6 col-md-4">
                    <a href="<?php echo htmlspecialchars($p); ?>" target="_blank">
                      <img src="<?php echo htmlspecialchars($p); ?>" class="img-fluid rounded" alt="">
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
      <div class="d-grid">
        <a class="btn btn-primary" href="#">Rent this room</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
