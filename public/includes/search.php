<?php
require_once __DIR__ . '/../../config/config.php';

$q = trim($_GET['q'] ?? '');
$items_props = [];
$items_rooms = [];

if ($q !== '') {
  $like = '%' . $q . '%';
  // Properties (available)
  $sp = db()->prepare('SELECT property_id, title, description, image, price_per_month, property_type, status FROM properties WHERE status = "available" AND (title LIKE ? OR description LIKE ?) ORDER BY property_id DESC LIMIT 50');
  $sp->bind_param('ss', $like, $like);
  $sp->execute();
  $rp = $sp->get_result();
  while ($row = $rp->fetch_assoc()) { $items_props[] = $row; }
  $sp->close();

  // Rooms (available)
  $sr = db()->prepare('SELECT room_id, title, room_type, beds, status, price_per_day FROM rooms WHERE status = "available" AND (title LIKE ? OR room_type LIKE ?) ORDER BY room_id DESC LIMIT 50');
  $sr->bind_param('ss', $like, $like);
  $sr->execute();
  $rr = $sr->get_result();
  while ($row = $rr->fetch_assoc()) { $items_rooms[] = $row; }
  $sr->close();
}

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Search</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="mb-4">
    <form class="row g-2" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <div class="col-12 col-md-8">
        <input class="form-control" type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search properties or rooms..." aria-label="Search" autocomplete="off" autofocus>
      </div>
      <div class="col-12 col-md-4 d-grid d-md-block">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
      </div>
    </form>
  </div>

  <?php if ($q === ''): ?>
    <div class="alert alert-light border">Type something to search available properties and rooms.</div>
  <?php else: ?>
    <h2 class="h5 mb-3">Properties</h2>
    <div class="row g-3 mb-4">
      <?php foreach ($items_props as $p): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100">
            <?php if (!empty($p['image'])): ?>
              <?php $img = $p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
              <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
              <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($p['property_type'] ?? '')); ?></div>
              <div class="text-muted mb-2 small text-uppercase"><?php echo htmlspecialchars($p['status']); ?></div>
              <div class="mt-auto fw-semibold"><?php echo money_lkr($p['price_per_month']); ?>/month</div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
                <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_property.php?id=<?php echo (int)$p['property_id']; ?>">Rent</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$items_props): ?>
        <div class="col-12"><div class="alert alert-light border">No properties found.</div></div>
      <?php endif; ?>
    </div>

    <h2 class="h5 mb-3">Rooms</h2>
    <div class="row g-3">
      <?php foreach ($items_rooms as $r): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($r['title']); ?></h5>
              <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($r['room_type'] ?? '')); ?> • Beds: <?php echo (int)$r['beds']; ?></div>
              <div class="text-muted mb-2 small text-uppercase"><?php echo htmlspecialchars($r['status']); ?></div>
              <div class="mt-auto fw-semibold"><?php echo money_lkr($r['price_per_day']); ?>/day</div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">View</a>
                <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_room.php?id=<?php echo (int)$r['room_id']; ?>">Rent</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$items_rooms): ?>
        <div class="col-12"><div class="alert alert-light border">No rooms found.</div></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
