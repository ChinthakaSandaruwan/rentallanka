<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cache.php';

// Current user (for wishlist state)
$uid = (int)($_SESSION['user']['user_id'] ?? 0);
// Pagination setup
$perPage = 9;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total count of available rooms (cached)
$cacheKeyTotal = 'all_rooms_total_v1';
$total = app_cache_get($cacheKeyTotal, 60);
if ($total === null) {
  $total = 0;
  $ctr = db()->query('SELECT COUNT(*) AS c FROM rooms r WHERE r.status = "available"');
  if ($ctr) { $row = $ctr->fetch_assoc(); $total = (int)($row['c'] ?? 0); $ctr->free(); }
  app_cache_set($cacheKeyTotal, $total);
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch current page of available rooms with primary image and location names (cached per user/page)
$cacheKeyPage = 'all_rooms_page_v1_' . $page . '_u' . $uid . '_pp' . $perPage;
$items = ($uid === 0) ? app_cache_get($cacheKeyPage, 60) : null;
if ($items === null) {
  $items = [];
  $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
  $whereKeyset = $lastId > 0 ? ' AND r.room_id < ?' : '';
  $limitClause = $lastId > 0 ? ' LIMIT ?' : ' LIMIT ? OFFSET ?';
  $sql = 'SELECT r.room_id, r.title, r.room_type, r.beds, r.price_per_day, r.status,
                 (
                   SELECT ri.image_path FROM room_images ri
                   WHERE ri.room_id = r.room_id
                   ORDER BY ri.is_primary DESC, ri.image_id DESC
                   LIMIT 1
                 ) AS image_path,
                 pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name,
                 ' . ($uid > 0 ? 'IF(rw.wishlist_id IS NULL, 0, 1)' : '0') . ' AS in_wishlist
          FROM rooms r
          LEFT JOIN room_locations l ON l.room_id = r.room_id
          LEFT JOIN provinces pr ON pr.id = l.province_id
          LEFT JOIN districts d ON d.id = l.district_id
          LEFT JOIN cities c ON c.id = l.city_id
          ' . ($uid > 0 ? 'LEFT JOIN room_wishlist rw ON rw.room_id = r.room_id AND rw.customer_id = ?' : '') . '
          WHERE r.status = "available"' . $whereKeyset . '
          ORDER BY r.room_id DESC' . $limitClause;
  $stmt = db()->prepare($sql);
  if ($uid > 0) {
    if ($lastId > 0) {
      $stmt->bind_param('iii', $uid, $lastId, $perPage);
    } else {
      $stmt->bind_param('iii', $uid, $perPage, $offset);
    }
  } else {
    if ($lastId > 0) {
      $stmt->bind_param('ii', $lastId, $perPage);
    } else {
      $stmt->bind_param('ii', $perPage, $offset);
    }
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) { $items[] = $row; }
  $stmt->close();
  if ($uid === 0) { app_cache_set($cacheKeyPage, $items); }
}

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php
    $seo = [
      'title' => 'All Rooms',
      'description' => 'Browse currently available rooms across Sri Lanka.',
      'url' => rtrim($base_url,'/') . '/public/includes/all_rooms.php',
      'type' => 'website'
    ];
    require_once __DIR__ . '/seo_meta.php';
  ?>
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
              <?php
                $path = parse_url($img, PHP_URL_PATH) ?: '';
                $webpUrl = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $img);
                $webpPath = ($webpUrl !== $img) ? (($_p = parse_url($webpUrl, PHP_URL_PATH)) ? rtrim($_SERVER['DOCUMENT_ROOT'] ?? '','/') . $_p : '') : '';
                $hasWebp = ($webpPath && is_file($webpPath));
              ?>
              <?php if ($hasWebp): ?>
                <picture>
                  <source type="image/webp" srcset="<?php echo htmlspecialchars($webpUrl); ?>">
                  <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy" decoding="async">
                </picture>
              <?php else: ?>
                <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy" decoding="async">
              <?php endif; ?>
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
            <div class="row g-2">
              <div class="col-6">
                <a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </div>
              <div class="col-6">
                <?php $in = (int)($r['in_wishlist'] ?? 0) === 1; ?>
                <button class="btn btn-sm w-100 btn-room-wish <?php echo $in ? 'btn-outline-danger' : 'btn-outline-primary'; ?>" data-id="<?php echo (int)$r['room_id']; ?>">
                  <?php if ($in): ?>
                    <i class="bi bi-heart-fill"></i> Added
                  <?php else: ?>
                    <i class="bi bi-heart"></i> Wishlist
                  <?php endif; ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <div class="col-12"><div class="alert alert-light border">No rooms found.</div></div>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <?php
      $makeUrl = function(int $p) use ($base_url) {
        return $base_url . '/public/includes/all_rooms.php?page=' . $p;
      };
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);
      if ($end - $start < 4) { $end = min($totalPages, $start + 4); }
      if ($end - $start < 4) { $start = max(1, $end - 4); }
    ?>
    <nav class="mt-4" aria-label="Rooms pagination">
      <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo htmlspecialchars($makeUrl($page - 1)); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true':'false'; ?>">Previous</a>
        </li>
        <?php if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($makeUrl(1)); ?>">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($makeUrl($i)); ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($makeUrl($totalPages)); ?>"><?php echo $totalPages; ?></a></li>
        <?php endif; ?>
        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo htmlspecialchars($makeUrl($page + 1)); ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  async function roomWishToggle(btn, id) {
    btn.disabled = true;
    try {
      const statusRes = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php?action=status&type=room&room_id=' + id);
      const s = await statusRes.json();
      const act = (s && s.in_wishlist) ? 'remove' : 'add';
      const res = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: act, type: 'room', room_id: String(id) })
      });
      const data = await res.json();
      if (data.status === 'success' || data.status === 'exists') {
        if (act === 'add') {
          btn.classList.remove('btn-outline-primary');
          btn.classList.add('btn-outline-danger');
          btn.innerHTML = '<i class="bi bi-heart-fill"></i> Added';
        } else {
          btn.classList.remove('btn-outline-danger');
          btn.classList.add('btn-outline-primary');
          btn.innerHTML = '<i class="bi bi-heart"></i> Wishlist';
        }
      } else if (data.status === 'error') {
        alert(data.message || 'Action failed');
      }
    } catch (e) {
      alert('Network error');
    } finally {
      btn.disabled = false;
    }
  }
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-room-wish');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-id') || '0', 10);
    if (!id) return;
    roomWishToggle(btn, id);
  });
</script>
</body>
</html>

