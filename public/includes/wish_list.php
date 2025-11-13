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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       WISHLIST PAGE CUSTOM STYLES
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
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
    }
    
    /* Page Header */
    .rl-page-header {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      padding: 1.5rem;
      box-shadow: var(--rl-shadow-sm);
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 1.875rem);
      font-weight: 800;
      color: var(--rl-text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex: 1;
    }
    
    .rl-page-title i {
      color: var(--rl-dark);
      font-size: 1.5rem;
    }
    
    .rl-count-badge {
      background: linear-gradient(135deg, var(--rl-accent) 0%, var(--rl-primary) 100%);
      color: var(--rl-white);
      padding: 0.375rem 0.75rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.875rem;
      margin-left: 0.5rem;
    }
    
    .rl-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    
    /* Buttons */
    .rl-btn {
      border-radius: 8px;
      font-weight: 600;
      padding: 0.5rem 1rem;
      transition: all 0.2s ease;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      border: none;
    }
    
    .rl-btn-outline {
      background: var(--rl-white);
      border: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
    }
    
    .rl-btn-outline:hover {
      background: rgba(0, 78, 152, 0.05);
      border-color: var(--rl-accent);
      color: var(--rl-accent);
      transform: translateY(-1px);
    }
    
    .rl-btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
      border: 2px solid transparent;
    }
    
    .rl-btn-primary:hover {
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.25);
      transform: translateY(-1px);
      color: var(--rl-white);
    }
    
    /* Wishlist Cards */
    .rl-wishlist-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.5rem;
    }
    
    .rl-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius);
      overflow: hidden;
      box-shadow: var(--rl-shadow-sm);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      height: 100%;
      border: 2px solid transparent;
    }
    
    .rl-card:hover {
      box-shadow: var(--rl-shadow-md);
      transform: translateY(-4px);
      border-color: var(--rl-accent);
    }
    
    .rl-card-media {
      position: relative;
      width: 100%;
      padding-top: 56.25%; /* 16:9 aspect ratio */
      overflow: hidden;
      background: var(--rl-light-bg);
    }
    
    .rl-card-media img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }
    
    .rl-card:hover .rl-card-media img {
      transform: scale(1.05);
    }
    
    .rl-badge-status {
      position: absolute;
      top: 0.75rem;
      left: 0.75rem;
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      border: 1px solid #6ee7b7;
      padding: 0.25rem 0.625rem;
      border-radius: 6px;
      font-weight: 700;
      font-size: 0.6875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      z-index: 2;
    }
    
    .rl-card-body {
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    
    .rl-card-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-text);
      margin: 0 0 0.5rem 0;
      line-height: 1.4;
    }
    
    .rl-card-type {
      font-size: 0.875rem;
      color: var(--rl-text-muted);
      margin-bottom: 0.75rem;
    }
    
    .rl-card-price {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-dark);
      margin-top: auto;
    }
    
    .rl-card-footer {
      padding: 0 1.25rem 1.25rem 1.25rem;
      display: flex;
      gap: 0.5rem;
    }
    
    .rl-btn-view {
      flex: 1;
      background: var(--rl-white);
      border: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
      padding: 0.625rem 1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.375rem;
      text-decoration: none;
    }
    
    .rl-btn-view:hover {
      background: rgba(0, 78, 152, 0.05);
      border-color: var(--rl-accent);
      color: var(--rl-accent);
      transform: translateY(-1px);
    }
    
    .rl-btn-remove {
      flex: 1;
      background: var(--rl-white);
      border: 2px solid #ef4444;
      color: #ef4444;
      padding: 0.625rem 1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.375rem;
      cursor: pointer;
    }
    
    .rl-btn-remove:hover:not(:disabled) {
      background: #ef4444;
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    .rl-btn-remove:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    /* Empty State */
    .rl-empty-state {
      text-align: center;
      padding: 4rem 2rem;
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-sm);
      border: 2px dashed var(--rl-border);
    }
    
    .rl-empty-state i {
      font-size: 4rem;
      color: var(--rl-secondary);
      margin-bottom: 1.5rem;
      display: block;
    }
    
    .rl-empty-state-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--rl-text);
      margin-bottom: 0.5rem;
    }
    
    .rl-empty-state-text {
      color: var(--rl-text-muted);
      font-size: 1rem;
      margin-bottom: 1.5rem;
    }
    
    .rl-empty-state .rl-btn {
      margin-top: 1rem;
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
    
    .rl-page-header {
      animation: fadeInUp 0.5s ease-out;
    }
    
    .rl-card {
      animation: fadeInUp 0.5s ease-out;
    }
    
    .rl-card:nth-child(2) { animation-delay: 0.05s; }
    .rl-card:nth-child(3) { animation-delay: 0.1s; }
    .rl-card:nth-child(4) { animation-delay: 0.15s; }
    .rl-card:nth-child(5) { animation-delay: 0.2s; }
    .rl-card:nth-child(6) { animation-delay: 0.25s; }
    
    /* Responsive Design */
    @media (max-width: 991px) {
      .rl-wishlist-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.25rem;
      }
    }
    
    @media (max-width: 767px) {
      .rl-container {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
      }
      
      .rl-page-header {
        padding: 1.25rem;
        flex-direction: column;
        align-items: flex-start;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }
      
      .rl-toolbar {
        width: 100%;
      }
      
      .rl-toolbar .rl-btn {
        flex: 1;
        justify-content: center;
      }
      
      .rl-wishlist-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .rl-empty-state {
        padding: 3rem 1.5rem;
      }
      
      .rl-empty-state i {
        font-size: 3rem;
      }
    }
    
    @media (max-width: 575px) {
      .rl-page-title {
        font-size: 1.25rem;
      }
      
      .rl-card-footer {
        flex-direction: column;
      }
      
      .rl-btn-view,
      .rl-btn-remove {
        width: 100%;
      }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container rl-container">
  <!-- Page Header -->
  <div class="rl-page-header">
    <h1 class="rl-page-title">
      <i class="bi bi-heart-fill"></i>
      Wishlist
      <span class="rl-count-badge" id="wishlistCount"><?php echo (int)(count($items) + count($items_rooms)); ?></span>
    </h1>
    <div class="rl-toolbar">
      <a href="<?php echo $base_url; ?>/public/includes/all_properties.php" class="rl-btn rl-btn-outline">
        <i class="bi bi-building"></i> Browse Properties
      </a>
      <a href="<?php echo $base_url; ?>/public/includes/all_rooms.php" class="rl-btn rl-btn-outline">
        <i class="bi bi-door-open"></i> Browse Rooms
      </a>
      <a href="<?php echo $base_url; ?>/" class="rl-btn rl-btn-primary">
        <i class="bi bi-house"></i> Home
      </a>
    </div>
  </div>

  <!-- Wishlist Grid -->
  <div class="rl-wishlist-grid" id="wishlistGrid">
    <?php foreach ($items as $p): ?>
      <div class="wishlist-card" data-type="property" data-id="<?php echo (int)$p['property_id']; ?>">
        <div class="rl-card">
          <div class="rl-card-media">
            <?php if (!empty($p['status'])): ?>
              <span class="rl-badge-status"><?php echo htmlspecialchars($p['status']); ?></span>
            <?php endif; ?>
            <?php if (!empty($p['image'])): ?>
              <?php $img = $p['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
              <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" loading="lazy" decoding="async">
            <?php endif; ?>
          </div>
          <div class="rl-card-body">
            <h5 class="rl-card-title"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="rl-card-type"><?php echo htmlspecialchars(ucfirst($p['property_type'] ?? '')); ?></div>
            <div class="rl-card-price"><?php echo money_lkr($p['price_per_month']); ?>/month</div>
          </div>
          <div class="rl-card-footer">
            <a class="rl-btn-view" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>">
              <i class="bi bi-eye"></i> View
            </a>
            <button class="rl-btn-remove btn-remove" data-type="property" data-id="<?php echo (int)$p['property_id']; ?>">
              <i class="bi bi-trash"></i> Remove
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php foreach ($items_rooms as $r): ?>
      <div class="wishlist-card" data-type="room" data-id="<?php echo (int)$r['room_id']; ?>">
        <div class="rl-card">
          <div class="rl-card-media">
            <?php if (!empty($r['status'])): ?>
              <span class="rl-badge-status"><?php echo htmlspecialchars($r['status']); ?></span>
            <?php endif; ?>
            <?php if (!empty($r['image'])): ?>
              <?php $imgR = $r['image']; if ($imgR && !preg_match('#^https?://#i', $imgR) && $imgR[0] !== '/') { $imgR = '/' . ltrim($imgR, '/'); } ?>
              <img src="<?php echo htmlspecialchars($imgR); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy" decoding="async">
            <?php endif; ?>
          </div>
          <div class="rl-card-body">
            <h5 class="rl-card-title"><?php echo htmlspecialchars($r['title']); ?></h5>
            <div class="rl-card-price"><?php echo money_lkr($r['price_per_day']); ?>/day</div>
          </div>
          <div class="rl-card-footer">
            <a class="rl-btn-view" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">
              <i class="bi bi-eye"></i> View
            </a>
            <button class="rl-btn-remove btn-remove" data-type="room" data-id="<?php echo (int)$r['room_id']; ?>">
              <i class="bi bi-trash"></i> Remove
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (!($items) && !($items_rooms)): ?>
      <div class="rl-empty-state" style="grid-column: 1 / -1;">
        <i class="bi bi-heart"></i>
        <h3 class="rl-empty-state-title">Your wishlist is empty</h3>
        <p class="rl-empty-state-text">Start adding properties and rooms you love!</p>
        <div>
          <a href="<?php echo $base_url; ?>/public/includes/all_properties.php" class="rl-btn rl-btn-primary">
            <i class="bi bi-building"></i> Browse Properties
          </a>
          <a href="<?php echo $base_url; ?>/public/includes/all_rooms.php" class="rl-btn rl-btn-outline">
            <i class="bi bi-door-open"></i> Browse Rooms
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-remove');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-id') || '0', 10);
    const typ = (btn.getAttribute('data-type') || 'property');
    if (!id) return;
    const what = typ === 'room' ? 'room' : 'property';
    if (window.Swal) {
      const res = await Swal.fire({ icon: 'warning', title: 'Remove from wishlist?', text: 'Remove this ' + what + ' from your wishlist?', showCancelButton: true, confirmButtonText: 'Yes, remove', cancelButtonText: 'Cancel' });
      if (!res.isConfirmed) return;
    } else {
      if (!confirm('Remove this ' + what + ' from your wishlist?')) return;
    }
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
        const badge = document.getElementById('wishlistCount');
        if (badge) { badge.textContent = String(Math.max(0, document.querySelectorAll('.wishlist-card').length)); }
        if (document.querySelectorAll('.wishlist-card').length === 0) {
          const grid = document.getElementById('wishlistGrid');
          const emptyState = document.createElement('div');
          emptyState.className = 'rl-empty-state';
          emptyState.style.gridColumn = '1 / -1';
          emptyState.innerHTML = `
            <i class="bi bi-heart"></i>
            <h3 class="rl-empty-state-title">Your wishlist is empty</h3>
            <p class="rl-empty-state-text">Start adding properties and rooms you love!</p>
            <div>
              <a href="<?php echo $base_url; ?>/public/includes/all_properties.php" class="rl-btn rl-btn-primary">
                <i class="bi bi-building"></i> Browse Properties
              </a>
              <a href="<?php echo $base_url; ?>/public/includes/all_rooms.php" class="rl-btn rl-btn-outline">
                <i class="bi bi-door-open"></i> Browse Rooms
              </a>
            </div>
          `;
          grid.appendChild(emptyState);
        }
      } else if (data.status === 'error') {
        const msg = String(data.message || 'Failed to remove.');
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonText: 'OK' });
        } else {
          alert(msg);
        }
      }
    } catch (err) {
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.', confirmButtonText: 'OK' });
      } else {
        alert('Network error.');
      }
    } finally {
      btn.disabled = false;
    }
  });
</script>
</body>
</html>
