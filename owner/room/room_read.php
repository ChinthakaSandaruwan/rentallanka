 <?php
 require_once __DIR__ . '/../../public/includes/auth_guard.php';
 require_role('owner');
 require_once __DIR__ . '/../../config/config.php';

 $uid = (int)($_SESSION['user']['user_id'] ?? 0);
 if ($uid <= 0) {
   redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
 }

 // Only use session flash, ignore GET params
 [$flash, $flash_type] = [get_flash()[0] ?? '', get_flash()[1] ?? ''];
 ?>
 <!doctype html>
 <html lang="en">
 <head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>My Rooms</title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 </head>
 <body>
 <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
 <div class="container py-4">

  <?php if (!empty($flash)): ?>
    <?php $map = ['error'=>'danger','danger'=>'danger','success'=>'success','warning'=>'warning','info'=>'info']; $type = $map[$flash_type ?? 'info'] ?? 'info'; ?>
    <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($flash); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php
    $rooms = [];
    $sql = 'SELECT r.room_id,
                   r.room_code,
                   r.title,
                   r.description,
                   r.room_type,
                   r.beds,
                   r.maximum_guests,
                   r.price_per_day,
                   r.created_at,
                   (SELECT image_path FROM room_images WHERE room_id=r.room_id AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1) AS image_path,
                   (SELECT COUNT(*) FROM room_images WHERE room_id=r.room_id AND COALESCE(is_primary,0)=0) AS gallery_count,
                   l.address, l.postal_code,
                   c.name_en AS city_name,
                   d.name_en AS district_name,
                   p.name_en AS province_name
            FROM rooms r
            LEFT JOIN locations l ON l.room_id = r.room_id
            LEFT JOIN cities c ON c.id = l.city_id
            LEFT JOIN districts d ON d.id = l.district_id
            LEFT JOIN provinces p ON p.id = l.province_id
            WHERE r.owner_id=?
            ORDER BY r.created_at DESC';
    $q = db()->prepare($sql);
    $q->bind_param('i', $uid);
    $q->execute();
    $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) { $rooms[] = $row; }
    $q->close();
  ?>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
    <?php foreach ($rooms as $r): ?>
      <div class="col">
        <div class="card h-100">
          <?php if (!empty($r['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($r['image_path']); ?>" class="card-img-top" alt="Room image">
          <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px;">
              <span class="text-muted">No image</span>
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h5 class="card-title mb-0"><?php echo htmlspecialchars($r['title'] ?: 'Untitled'); ?></h5>
              <?php if (!empty($r['room_code'])): ?>
                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($r['room_code']); ?></span>
              <?php endif; ?>
            </div>
            <div class="mb-2 small text-muted">LKR <?php echo number_format((float)($r['price_per_day'] ?? 0), 2); ?> / day</div>
            <div class="mb-2">
              <?php if (!empty($r['room_type'])): ?>
                <span class="badge bg-info text-dark me-1"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $r['room_type']))); ?></span>
              <?php endif; ?>
              <?php if (isset($r['beds'])): ?>
                <span class="badge bg-light text-dark border me-1">Beds: <?php echo (int)$r['beds']; ?></span>
              <?php endif; ?>
              <?php if (isset($r['maximum_guests'])): ?>
                <span class="badge bg-light text-dark border me-1">Guests: <?php echo (int)$r['maximum_guests']; ?></span>
              <?php endif; ?>
              <?php if (!empty($r['gallery_count'])): ?>
                <span class="badge bg-light text-dark border">Gallery: <?php echo (int)$r['gallery_count']; ?></span>
              <?php endif; ?>
            </div>
            <?php
              $locParts = [];
              if (!empty($r['city_name'])) $locParts[] = $r['city_name'];
              if (!empty($r['district_name'])) $locParts[] = $r['district_name'];
              if (!empty($r['province_name'])) $locParts[] = $r['province_name'];
              $locLine = implode(', ', array_map('htmlspecialchars', $locParts));
            ?>
            <?php if ($locLine || !empty($r['postal_code'])): ?>
              <div class="mb-2">
                <i class="bi bi-geo-alt text-muted"></i>
                <span class="text-muted"><?php echo $locLine; ?><?php echo $locLine && !empty($r['postal_code']) ? ' • ' : ''; ?><?php echo !empty($r['postal_code']) ? htmlspecialchars($r['postal_code']) : ''; ?></span>
              </div>
            <?php endif; ?>
            <?php if (!empty($r['address'])): ?>
              <div class="mb-2 text-muted small"><?php echo htmlspecialchars($r['address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($r['description'])): ?>
              <div class="mt-auto text-truncate" style="-webkit-line-clamp: 3; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;">
                <?php echo htmlspecialchars($r['description']); ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($r['created_at'])): ?>
              <div class="mt-2 small text-muted">
                <i class="bi bi-calendar-event"></i>
                Created: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['created_at']))); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($rooms)): ?>
      <div class="col">
        <div class="alert alert-info">You have not created any rooms yet.</div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/room_read.js" defer></script>
</body>
</html>

