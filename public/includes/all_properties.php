<?php
require_once __DIR__ . '/../../config/config.php';

// Current user (for wishlist state)
$uid = (int)($_SESSION['user']['user_id'] ?? 0);


// Pagination setup
$perPage = 9;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total count of available properties
$total = 0;
$ctr = db()->query('SELECT COUNT(*) AS c FROM properties p WHERE p.status = "available"');
if ($ctr) { $row = $ctr->fetch_assoc(); $total = (int)($row['c'] ?? 0); $ctr->free(); }
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch current page results with optional location info
$items_props = [];
$sql = 'SELECT p.property_id, p.title, p.description, p.image, p.price_per_month, p.property_type, p.status,
               pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name, l.address, l.postal_code,
               ' . ($uid > 0 ? 'IF(w.wishlist_id IS NULL, 0, 1)' : '0') . ' AS in_wishlist
        FROM properties p
        LEFT JOIN locations l ON l.property_id = p.property_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        ' . ($uid > 0 ? 'LEFT JOIN wishlist w ON w.property_id = p.property_id AND w.customer_id = ?' : '') . '
        WHERE p.status = "available"
        ORDER BY p.property_id DESC
        LIMIT ? OFFSET ?';
$stmt = db()->prepare($sql);
if ($uid > 0) {
  $stmt->bind_param('iii', $uid, $perPage, $offset);
} else {
  $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items_props[] = $row; }
$stmt->close();

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h1 class="h4 mb-1 d-flex align-items-center"><i class="bi bi-building me-2"></i>All Properties <span class="badge bg-secondary ms-2"><?php echo (int)$total; ?></span></h1>
      <div class="text-muted small">Browse currently available listings</div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo $base_url; ?>/public/includes/advance_search_property.php" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Advanced Search</a>
      <a href="<?php echo $base_url; ?>/" class="btn btn-outline-secondary btn-sm">Home</a>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    <?php foreach ($items_props as $p): ?>
      <div class="col">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($p['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($p['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
            <?php $img = $p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($p['property_type'] ?? '')); ?></div>
            <?php $loc = trim(implode(', ', array_filter([($p['city_name'] ?? ''), ($p['district_name'] ?? ''), ($p['province_name'] ?? '')]))); if ($loc !== ''): ?>
              <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($loc); ?></div>
            <?php endif; ?>
            <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 120, '…')); ?></p>
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
                <?php $in = (int)($p['in_wishlist'] ?? 0) === 1; ?>
                <button class="btn btn-sm w-100 btn-wish <?php echo $in ? 'btn-outline-danger' : 'btn-outline-primary'; ?>" data-id="<?php echo (int)$p['property_id']; ?>">
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
    <?php if (!$items_props): ?>
      <div class="col-12"><div class="alert alert-light border">No properties found.</div></div>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <?php
      $makeUrl = function(int $p) use ($base_url) {
        return $base_url . '/public/includes/all_properties.php?page=' . $p;
      };
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);
      if ($end - $start < 4) { $end = min($totalPages, $start + 4); }
      if ($end - $start < 4) { $start = max(1, $end - 4); }
    ?>
    <nav class="mt-4" aria-label="Properties pagination">
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    async function wishToggle(btn, id) {
      btn.disabled = true;
      try {
        const statusRes = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php?action=status&property_id=' + id);
        const s = await statusRes.json();
        const act = (s && s.in_wishlist) ? 'remove' : 'add';
        const res = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: act, property_id: String(id) })
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
      const btn = e.target.closest('.btn-wish');
      if (!btn) return;
      const id = parseInt(btn.getAttribute('data-id') || '0', 10);
      if (!id) return;
      wishToggle(btn, id);
    });
  </script>
</body>
</html>
