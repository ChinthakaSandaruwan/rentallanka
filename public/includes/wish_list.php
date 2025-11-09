<?php
require_once __DIR__ . '/../../config/config.php';

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$sessionUser = $_SESSION['user'] ?? null;
$uid = (int)($sessionUser['user_id'] ?? 0);
if (!$loggedIn || $uid <= 0) {
    redirect_with_message($base_url . '/auth/login.php', 'Please log in first', 'error');
}

// Fetch wishlist items joined with properties
$items = [];
$sql = 'SELECT 
  w.wishlist_id,
  p.property_id,
  p.title,
  p.price_per_month,
  p.image,
  p.status,
  p.property_type
FROM wishlist w
JOIN properties p ON w.property_id = p.property_id
WHERE w.customer_id = ?
ORDER BY w.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();

// Fetch wishlist items joined with rooms
$items_rooms = [];
$sqlR = 'SELECT 
  rw.wishlist_id,
  r.room_id,
  r.title,
  r.price_per_day,
  r.status,
  (
    SELECT ri.image_path FROM room_images ri
    WHERE ri.room_id = r.room_id
    ORDER BY ri.is_primary DESC, ri.image_id DESC
    LIMIT 1
  ) AS image
FROM room_wishlist rw
JOIN rooms r ON rw.room_id = r.room_id
WHERE rw.customer_id = ?
ORDER BY rw.created_at DESC';
$stR = db()->prepare($sqlR);
$stR->bind_param('i', $uid);
$stR->execute();
$reR = $stR->get_result();
while ($row = $reR->fetch_assoc()) { $items_rooms[] = $row; }
$stR->close();

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Wishlist</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @media (max-width: 576px){
      h1.h4{font-size:1.1rem}
      .wishlist-toolbar .btn{padding:.4rem .75rem;font-size:.9rem}
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-3 gap-2">
    <div>
      <h1 class="h4 mb-1 d-flex align-items-center"><i class="bi bi-heart me-2"></i>Wishlist <span class="badge bg-secondary ms-2"><?php echo (int)(count($items) + count($items_rooms)); ?></span></h1>
    </div>
    <div class="d-flex flex-wrap gap-2 wishlist-toolbar w-100 w-md-auto">
      <a href="<?php echo $base_url; ?>/public/includes/all_properties.php" class="btn btn-outline-secondary btn-sm">Browse Properties</a>
      <a href="<?php echo $base_url; ?>/public/includes/all_rooms.php" class="btn btn-outline-secondary btn-sm">Browse Rooms</a>
      <a href="<?php echo $base_url; ?>/" class="btn btn-secondary btn-sm">Home</a>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3" id="wishlistGrid">
    <?php foreach ($items as $p): ?>
      <div class="col wishlist-card" data-type="property" data-id="<?php echo (int)$p['property_id']; ?>">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($p['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($p['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
            <?php $img = $p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($p['property_type'] ?? '')); ?></div>
            <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($p['price_per_month']); ?>/month</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="row g-2">
              <div class="col-6">
                <a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </div>
              <div class="col-6">
                <button class="btn btn-sm btn-outline-danger w-100 btn-remove" data-type="property" data-id="<?php echo (int)$p['property_id']; ?>">
                  <i class="bi bi-trash me-1"></i>Remove
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php foreach ($items_rooms as $r): ?>
      <div class="col wishlist-card" data-type="room" data-id="<?php echo (int)$r['room_id']; ?>">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($r['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($r['image'])): ?>
            <?php $imgR = $r['image']; if ($imgR && !preg_match('#^https?://#i', $imgR) && $imgR[0] !== '/') { $imgR = '/' . ltrim($imgR, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($imgR); ?>" class="w-100 h-100 object-fit-cover" alt="" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($r['title']); ?></h5>
            <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($r['price_per_day']); ?>/day</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="row g-2">
              <div class="col-6">
                <a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </div>
              <div class="col-6">
                <button class="btn btn-sm btn-outline-danger w-100 btn-remove" data-type="room" data-id="<?php echo (int)$r['room_id']; ?>">
                  <i class="bi bi-trash me-1"></i>Remove
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (!($items) && !($items_rooms)): ?>
      <div class="col-12"><div class="alert alert-light border">No items in your wishlist yet.</div></div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-remove');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-id') || '0', 10);
    const typ = (btn.getAttribute('data-type') || 'property');
    if (!id) return;
    const what = typ === 'room' ? 'room' : 'property';
    if (!confirm('Remove this ' + what + ' from your wishlist?')) return;
    btn.disabled = true;
    try {
      const resp = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(Object.assign({ action: 'remove' }, (typ === 'room' ? { type: 'room', room_id: String(id) } : { property_id: String(id) })))
      });
      const data = await resp.json();
      if (data.status === 'success') {
        const card = btn.closest('.wishlist-card');
        if (card) card.remove();
        // Update count badge
        const badge = document.querySelector('h1 .badge');
        if (badge) { badge.textContent = String(Math.max(0, document.querySelectorAll('.wishlist-card').length)); }
        if (document.querySelectorAll('.wishlist-card').length === 0) {
          const grid = document.getElementById('wishlistGrid');
          const wrap = document.createElement('div');
          wrap.className = 'col-12';
          wrap.innerHTML = '<div class="alert alert-light border">No items in your wishlist yet.</div>';
          grid.appendChild(wrap);
        }
      } else if (data.status === 'error') {
        alert(data.message || 'Failed to remove.');
      }
    } catch (err) {
      alert('Network error.');
    } finally {
      btn.disabled = false;
    }
  });
</script>
</body>
</html>
