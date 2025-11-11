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
// Current user (for wishlist state)
$uid = (int)($_SESSION['user']['user_id'] ?? 0);

$q = trim($_GET['q'] ?? '');
$province_id = (int)($_GET['province_id'] ?? 0);
$district_id = (int)($_GET['district_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);

$conds = ["r.status = 'available'"];
$types = '';
$vals = [];
if ($q !== '') {
  $like = '%' . $q . '%';
  $conds[] = '(r.title LIKE ? OR r.room_type LIKE ? OR pr.name_en LIKE ? OR d.name_en LIKE ? OR c.name_en LIKE ? OR l.address LIKE ? OR l.postal_code LIKE ?)';
  $types .= 'sssssss';
  array_push($vals, $like, $like, $like, $like, $like, $like, $like);
}
if ($province_id) { $conds[] = 'l.province_id = ?'; $types .= 'i'; $vals[] = $province_id; }
if ($district_id) { $conds[] = 'l.district_id = ?'; $types .= 'i'; $vals[] = $district_id; }
if ($city_id) { $conds[] = 'l.city_id = ?'; $types .= 'i'; $vals[] = $city_id; }

// Pagination
$perPage = 8;
$pageRoom = isset($_GET['page_room']) ? max(1, (int)$_GET['page_room']) : 1;
$offset = ($pageRoom - 1) * $perPage;

// Count total items with filters
$needLookupJoins = ($q !== '');
$countSql = "SELECT COUNT(*) AS c
             FROM rooms r
             LEFT JOIN room_locations l ON l.room_id = r.room_id" .
             ($needLookupJoins ? "
             LEFT JOIN provinces pr ON pr.id = l.province_id
             LEFT JOIN districts d ON d.id = l.district_id
             LEFT JOIN cities c ON c.id = l.city_id" : "") .
            "
             WHERE " . implode(' AND ', $conds);
$stc = db()->prepare($countSql);
if ($types !== '') { $stc->bind_param($types, ...$vals); }
$stc->execute();
$rc = $stc->get_result();
$rowc = $rc ? $rc->fetch_assoc() : ['c'=>0];
$total = (int)($rowc['c'] ?? 0);
$stc->close();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($pageRoom > $totalPages) { $pageRoom = $totalPages; $offset = ($pageRoom - 1) * $perPage; }

// Fetch page results
$sql = "SELECT r.room_id, r.title, r.room_type, r.beds, r.price_per_day, r.status,
               (
                 SELECT ri.image_path FROM room_images ri
                 WHERE ri.room_id = r.room_id
                 ORDER BY ri.is_primary DESC, ri.image_id DESC
                 LIMIT 1
               ) AS image_path,
               " . ($uid > 0 ? "IF(rw.wishlist_id IS NULL, 0, 1)" : "0") . " AS in_wishlist
        FROM rooms r
        LEFT JOIN room_locations l ON l.room_id = r.room_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        " . ($uid > 0 ? "LEFT JOIN room_wishlist rw ON rw.room_id = r.room_id AND rw.customer_id = ? " : "") .
        "WHERE " . implode(' AND ', $conds) . "
        ORDER BY r.room_id DESC
        LIMIT ? OFFSET ?";

$items = [];
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
$items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>
<style>
  /* ===========================
     ROOM LISTING CUSTOM STYLES
     Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
     Design matches all_properties.php
     =========================== */
  
  /* Theme Variables */
  .rl-theme {
    --rl-primary: #004E98;
    --rl-light: #EBEBEB;
    --rl-secondary: #C0C0C0;
    --rl-accent: #3A6EA5;
    --rl-dark: #FF6700;
    --rl-bg: #ffffff;
    --rl-text: #1f2a37;
    --rl-muted: #6b7280;
    --rl-border: #E5E7EB;
    --rl-shadow: 0 6px 24px rgba(0,0,0,.08);
    --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
    --rl-radius: 12px;
  }
  
  /* Section Container */
  .rl-section {
    padding: clamp(1.25rem, 1.6vw + 0.5rem, 2.25rem) 0;
  }
  
  /* Listing Cards */
  .rl-listing-card {
    border: 1px solid var(--rl-border);
    border-radius: var(--rl-radius);
    overflow: hidden;
    background: #fff;
    box-shadow: var(--rl-shadow-sm);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
  }
  
  .rl-listing-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--rl-shadow);
    border-color: rgba(0,78,152,.25);
  }
  
  /* Card Image Section */
  .rl-listing-media {
    background: var(--rl-light);
    position: relative;
  }
  
  /* Status Badge */
  .rl-badge {
    position: absolute;
    top: .75rem;
    left: .75rem;
    background: rgba(255,255,255,.92);
    color: var(--rl-primary);
    border: 1px solid var(--rl-primary);
    border-radius: 999px;
    padding: .25rem .6rem;
    font-weight: 700;
    font-size: .8rem;
    box-shadow: var(--rl-shadow-sm);
    text-transform: uppercase;
    z-index: 10;
  }
  
  /* Card Body */
  .rl-listing-body {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: .5rem;
    flex: 1;
  }
  
  .rl-listing-body .card-title {
    font-weight: 700;
    color: var(--rl-text);
    font-size: 1.125rem;
    line-height: 1.3;
    margin-bottom: 0.25rem;
  }
  
  /* Price Display */
  .rl-price {
    color: var(--rl-dark);
    font-weight: 800;
    letter-spacing: .2px;
    font-size: 1.125rem;
  }
  
  /* Meta Info */
  .rl-meta {
    color: var(--rl-muted);
    font-size: 0.875rem;
  }
  
  /* Buttons */
  .rl-btn {
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: .2px;
    transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, color .2s ease, border-color .2s ease;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }
  
  .rl-btn:active {
    transform: translateY(1px);
  }
  
  .rl-btn-outline {
    background: #fff;
    color: var(--rl-primary);
    border-color: var(--rl-primary);
  }
  
  .rl-btn-outline:hover {
    background: rgba(0,78,152,.06);
    border-color: var(--rl-primary);
    color: var(--rl-primary);
  }
  
  .btn-outline-secondary.rl-btn {
    border-color: var(--rl-border);
    color: var(--rl-muted);
  }
  
  .btn-outline-secondary.rl-btn:hover {
    background: rgba(0,0,0,.03);
    border-color: var(--rl-secondary);
  }
  
  /* Pagination */
  .rl-page-link {
    border-radius: 8px !important;
    border-color: var(--rl-secondary);
    color: var(--rl-text);
    margin: 0 0.125rem;
    transition: all 0.2s ease;
  }
  
  .rl-page-link:hover {
    background-color: rgba(0,78,152,.06);
    border-color: var(--rl-primary);
    color: var(--rl-primary);
  }
  
  .rl-page-item.active .rl-page-link {
    background-color: var(--rl-primary);
    border-color: var(--rl-primary);
    color: #fff;
  }
  
  /* Responsive Adjustments */
  @media (max-width: 991px) {
    .rl-listing-card {
      border-radius: 10px;
    }
    
    .rl-listing-body {
      padding: 0.875rem;
    }
  }
  
  @media (max-width: 767px) {
    .rl-listing-card {
      border-radius: 8px;
    }
    
    .rl-listing-body .card-title {
      font-size: 1rem;
    }
    
    .rl-price {
      font-size: 1rem;
    }
    
    .rl-btn {
      font-size: 0.8125rem;
      padding: 0.4rem 0.75rem;
    }
  }
</style>

<section id="rooms-section" class="rl-theme container rl-section">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0 fw-bold" style="color: var(--rl-text);"><i class="bi bi-door-open me-2"></i>Rooms</h2>
  </div>
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
    <?php foreach ($items as $r): ?>
      <div class="col">
        <div class="card rl-listing-card position-relative">
          <?php if (!empty($r['status'])): ?>
            <span class="rl-badge"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($r['image_path'])): ?>
            <?php $src = $r['image_path']; if ($src && !preg_match('#^https?://#i', $src) && $src[0] !== '/') { $src = '/'.ltrim($src, '/'); } ?>
            <div class="ratio ratio-16x9 rl-listing-media">
              <img src="<?php echo htmlspecialchars($src); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
          <div class="card-body rl-listing-body">
            <h5 class="card-title"><?php echo htmlspecialchars($r['title']); ?></h5>
            <div class="rl-meta small mb-1"><?php echo htmlspecialchars(ucfirst($r['room_type'])); ?> • Beds: <?php echo (int)$r['beds']; ?></div>
            <div class="mt-auto rl-price">LKR <?php echo number_format((float)$r['price_per_day'], 2); ?>/day</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="row g-2">
              <div class="col-6">
                <a class="btn rl-btn btn-sm btn-outline-secondary w-100" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </div>
              <div class="col-6">
                <?php $in = (int)($r['in_wishlist'] ?? 0) === 1; ?>
                <button class="btn rl-btn btn-sm w-100 btn-room-wish <?php echo $in ? 'btn-outline-danger' : 'btn-outline-primary'; ?>" data-id="<?php echo (int)$r['room_id']; ?>">
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
      // Build pagination links preserving filters
      $qs = [];
      if ($q !== '') $qs['q'] = $q;
      if ($province_id) $qs['province_id'] = (string)$province_id;
      if ($district_id) $qs['district_id'] = (string)$district_id;
      if ($city_id) $qs['city_id'] = (string)$city_id;
      $inSearch = ($q !== '' || $province_id || $district_id || $city_id);
      $makeUrl = function(int $p) use ($base_url, $qs, $inSearch) {
        $params = array_merge($qs, ['page_room' => $p]);
        if ($inSearch) {
          return $base_url . '/public/includes/search.php?' . http_build_query($params);
        }
        return $base_url . '/public/includes/all_rooms.php?' . http_build_query(['page' => $p]);
      };
      $start = max(1, $pageRoom - 2);
      $end = min($totalPages, $pageRoom + 2);
      if ($end - $start < 4) { $end = min($totalPages, $start + 4); }
      if ($end - $start < 4) { $start = max(1, $end - 4); }
    ?>
    <nav class="mt-4" aria-label="Rooms pagination">
      <ul class="pagination justify-content-center">
        <li class="page-item rl-page-item <?php echo $pageRoom <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link rl-page-link" href="<?php echo htmlspecialchars($makeUrl($pageRoom - 1)); ?>" tabindex="-1" aria-disabled="<?php echo $pageRoom <= 1 ? 'true' : 'false'; ?>">Previous</a>
        </li>
        <?php if ($start > 1): ?>
          <li class="page-item rl-page-item"><a class="page-link rl-page-link" href="<?php echo htmlspecialchars($makeUrl(1)); ?>">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link rl-page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item rl-page-item <?php echo $i === $pageRoom ? 'active' : ''; ?>">
            <a class="page-link rl-page-link" href="<?php echo htmlspecialchars($makeUrl($i)); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link rl-page-link">…</span></li><?php endif; ?>
          <li class="page-item rl-page-item"><a class="page-link rl-page-link" href="<?php echo htmlspecialchars($makeUrl($totalPages)); ?>"><?php echo $totalPages; ?></a></li>
        <?php endif; ?>
        <li class="page-item rl-page-item <?php echo $pageRoom >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link rl-page-link" href="<?php echo htmlspecialchars($makeUrl($pageRoom + 1)); ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</section>
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
