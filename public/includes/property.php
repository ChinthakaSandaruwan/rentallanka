<?php
require_once __DIR__ . '/../../config/config.php';
// Current user for wishlist state
$uid = (int)($_SESSION['user']['user_id'] ?? 0);


$q = trim($_GET['q'] ?? '');
$province_id = (int)($_GET['province_id'] ?? 0);
$district_id = (int)($_GET['district_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);

$conds = ["p.status = 'available'"];
$types = '';
$vals = [];
if ($q !== '') {
  $like = '%' . $q . '%';
  $conds[] = '(p.title LIKE ? OR p.description LIKE ? OR pr.name_en LIKE ? OR d.name_en LIKE ? OR c.name_en LIKE ? OR l.address LIKE ? OR l.postal_code LIKE ?)';
  $types .= 'sssssss';
  array_push($vals, $like, $like, $like, $like, $like, $like, $like);
}
if ($province_id) { $conds[] = 'l.province_id = ?'; $types .= 'i'; $vals[] = $province_id; }
if ($district_id) { $conds[] = 'l.district_id = ?'; $types .= 'i'; $vals[] = $district_id; }
if ($city_id) { $conds[] = 'l.city_id = ?'; $types .= 'i'; $vals[] = $city_id; }

// Pagination
$perPage = 8;
$pageProp = isset($_GET['page_prop']) ? max(1, (int)$_GET['page_prop']) : 1;
$offset = ($pageProp - 1) * $perPage;

// Count total
$needLookupJoins = ($q !== '');
$countSql = 'SELECT COUNT(*) AS c '
          . 'FROM properties p '
          . 'LEFT JOIN property_locations l ON l.property_id = p.property_id '
          . ($needLookupJoins ? 'LEFT JOIN provinces pr ON pr.id = l.province_id LEFT JOIN districts d ON d.id = l.district_id LEFT JOIN cities c ON c.id = l.city_id ' : '')
          . 'WHERE ' . implode(' AND ', $conds);
$stc = db()->prepare($countSql);
if ($types !== '') { $stc->bind_param($types, ...$vals); }
$stc->execute();
$rc = $stc->get_result();
$rowc = $rc ? $rc->fetch_assoc() : ['c'=>0];
$total = (int)($rowc['c'] ?? 0);
$stc->close();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($pageProp > $totalPages) { $pageProp = $totalPages; $offset = ($pageProp - 1) * $perPage; }

// Fetch page data
$sql = 'SELECT p.property_id, p.title, p.price_per_month, p.image, p.status,
               ' . ($uid > 0 ? 'IF(w.wishlist_id IS NULL, 0, 1)' : '0') . ' AS in_wishlist
        FROM properties p
        LEFT JOIN property_locations l ON l.property_id = p.property_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        ' . ($uid > 0 ? 'LEFT JOIN wishlist w ON w.property_id = p.property_id AND w.customer_id = ? ' : '') .
        'WHERE ' . implode(' AND ', $conds) . ' ORDER BY p.property_id DESC LIMIT ? OFFSET ?';

$items = [];
// add types for limit/offset
$stmt = db()->prepare($sql);
if ($uid > 0) {
  $bindTypes = 'i' . $types . 'ii';
  $params = array_merge([$uid], $vals, [$perPage, $offset]);
  $stmt->bind_param($bindTypes, ...$params);
} else {
  $bindTypes = $types . 'ii';
  if ($types !== '') { $stmt->bind_param($bindTypes, ...array_merge($vals, [$perPage, $offset])); }
  else { $stmt->bind_param('ii', $perPage, $offset); }
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();
?>
<section id="properties-section" class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><i class="bi bi-building me-1"></i>Properties</h2>
    <a href="owner/property_management.php" class="btn btn-sm btn-outline-primary d-none">View all</a>
  </div>
  <div class="row g-3">
    <?php foreach ($items as $p): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border shadow-sm position-relative overflow-hidden">
          <?php if (!empty($p['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($p['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
            <?php $src = $p['image']; if ($src && !preg_match('#^https?://#i', $src) && $src[0] !== '/') { $src = '/'.ltrim($src, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($src); ?>" class="w-100 h-100 object-fit-cover" alt="" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="mt-auto">
              <span class="fw-semibold">LKR <?php echo number_format((float)$p['price_per_month'], 2); ?>/month</span>
            </div>
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
    <?php if (!$items): ?>
      <div class="col-12"><div class="alert alert-light border">No properties found.</div></div>
    <?php endif; ?>
  </div>
  <?php if ($totalPages > 1): ?>
    <?php
      // Build pagination links preserving filters
      $qs = [];
      if ($q !== '') $qs['q'] = $q;
      if ($province_id) $qs['province_id'] = (string)$province_id;
      if ($district_id) $qs['district_id'] = (string)$district_id;
      if ($city_id) $qs['city_id'] = (string)$city_id;
      if (!empty($_GET['scope']) && in_array($_GET['scope'], ['all','properties','rooms'], true)) {
        $qs['scope'] = $_GET['scope'];
      }
      $inSearch = ($q !== '' || $province_id || $district_id || $city_id);
      $makeUrl = function(int $p) use ($base_url, $qs, $inSearch) {
        $params = array_merge($qs, ['page_prop' => $p]);
        if ($inSearch) {
          return $base_url . '/public/includes/search.php?' . http_build_query($params);
        }
        return $base_url . '/public/includes/all_properties.php?' . http_build_query(['page' => $p]);
      };
      $start = max(1, $pageProp - 2);
      $end = min($totalPages, $pageProp + 2);
      if ($end - $start < 4) { $end = min($totalPages, $start + 4); }
      if ($end - $start < 4) { $start = max(1, $end - 4); }
    ?>
    <nav class="mt-3" aria-label="Properties pagination">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?php echo $pageProp <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo htmlspecialchars($makeUrl($pageProp - 1)); ?>">Previous</a>
        </li>
        <?php if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($makeUrl(1)); ?>">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?php echo $i === $pageProp ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo htmlspecialchars($makeUrl($i)); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($makeUrl($totalPages)); ?>"><?php echo $totalPages; ?></a></li>
        <?php endif; ?>
        <li class="page-item <?php echo $pageProp >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link" href="<?php echo htmlspecialchars($makeUrl($pageProp + 1)); ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</section>
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
