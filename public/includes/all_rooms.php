<?php
require_once __DIR__ . '/../../config/config.php';

// Fetch latest available rooms with primary image and location names
$items = [];
$sql = 'SELECT r.room_id, r.title, r.room_type, r.beds, r.price_per_day, r.status,
               (
                 SELECT ri.image_path FROM room_images ri
                 WHERE ri.room_id = r.room_id
                 ORDER BY ri.is_primary DESC, ri.image_id DESC
                 LIMIT 1
               ) AS image_path,
               pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name
        FROM rooms r
        LEFT JOIN locations l ON l.room_id = r.room_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        WHERE r.status = "available"
        ORDER BY r.room_id DESC
        LIMIT 100';
$res = db()->query($sql);
if ($res) { while ($row = $res->fetch_assoc()) { $items[] = $row; } $res->free(); }
$total = count($items);

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Rooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h1 class="h4 mb-1 d-flex align-items-center"><i class="bi bi-door-open me-2"></i>All Rooms <span class="badge bg-secondary ms-2"><?php echo (int)$total; ?></span></h1>
      <div class="text-muted small">Browse currently available rooms</div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo $base_url; ?>/public/includes/advance_search_room.php" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Advanced Search</a>
      <a href="<?php echo $base_url; ?>/" class="btn btn-outline-secondary btn-sm">Home</a>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    <?php foreach ($items as $r): ?>
      <div class="col">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($r['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($r['image_path'])): ?>
            <?php $img = $r['image_path']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($r['title']); ?></h5>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($r['room_type'] ?? '')); ?> • Beds: <?php echo (int)$r['beds']; ?></div>
            <?php $loc = trim(implode(', ', array_filter([($r['city_name'] ?? ''), ($r['district_name'] ?? ''), ($r['province_name'] ?? '')]))); if ($loc !== ''): ?>
              <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($loc); ?></div>
            <?php endif; ?>
            <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($r['price_per_day']); ?>/day</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>"><i class="bi bi-eye me-1"></i>View</a>
              <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_room.php?id=<?php echo (int)$r['room_id']; ?>"><i class="bi bi-bag-plus me-1"></i>Rent</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <div class="col-12"><div class="alert alert-light border">No rooms found.</div></div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

