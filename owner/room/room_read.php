 <?php
 ini_set('display_errors', 0);
 ini_set('log_errors', 1);
 ini_set('error_log', ___DIR___ . '/../../error/error.log');

 if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
   $f = ___DIR___ . '/../../error/error.log';
   if (is_readable($f)) {
     $lines = 100; $data = '';
     $fp = fopen($f, 'r');
     if ($fp) {
       fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
       while ($pos > 0 && $ln <= $lines) {
         $step = max(0, $pos - 4096); $read = $pos - $step;
         fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
         $ln = substr_count($chunk, "\n");
       }
       fclose($fp);
       $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
     }
     header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
   }
 }

 require_once ___DIR___ . '/../../public/includes/auth_guard.php';
 require_role('owner');
 require_once ___DIR___ . '/../../config/config.php';

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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       ROOM READ - Shared Design System (rl-*)
       =========================== */
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.5rem 2rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.75rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1.1rem 1.5rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1.05rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    /* Room cards */
    .room-card { position:relative; transition:all .3s cubic-bezier(.4,0,.2,1); }
    .room-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .25s ease; }
    .room-card:hover { transform:translateY(-6px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent) !important; }
    .room-card:hover::before { opacity:1; }
    .card-img-top { object-fit:cover; height: 200px; }
    .placeholder-img { background:linear-gradient(135deg,#eef2f7 0%, #e2e8f0 100%); color:#64748b; height:200px; }

    .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

    @media (max-width: 767px){ .rl-page-header{ padding:1.1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } .card-img-top,.placeholder-img{ height:180px; } }
  </style>
</head>
<body>
<?php require_once ___DIR___ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-door-open"></i> My Rooms</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>
  <?php if (!empty($flash)): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-body py-3">
        <div class="d-flex align-items-start">
          <i class="bi <?php $map=['error'=>'bi-exclamation-triangle text-warning','danger'=>'bi-exclamation-triangle text-warning','success'=>'bi-check-circle text-success','warning'=>'bi-exclamation-triangle text-warning','info'=>'bi-info-circle text-primary']; echo $map[$flash_type ?? 'info'] ?? 'bi-info-circle text-primary'; ?> me-2"></i>
          <div><?php echo htmlspecialchars($flash); ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="rl-form-card">
    <div class="rl-form-header">
      <h2 class="rl-form-header-title"><i class="bi bi-card-list"></i> Your Rooms</h2>
    </div>
    <div class="rl-form-body">
      <div class="row g-3">
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
                         r.status,
                         (SELECT image_path FROM room_images WHERE room_id=r.room_id AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1) AS image_path,
                         (SELECT COUNT(*) FROM room_images WHERE room_id=r.room_id AND COALESCE(is_primary,0)=0) AS gallery_count,
                         l.address,
                         l.postal_code,
                         c.name_en AS city_name,
                         d.name_en AS district_name,
                         p.name_en AS province_name
                  FROM rooms r
                  LEFT JOIN room_locations l ON l.room_id = r.room_id
                  LEFT JOIN cities c ON c.id = l.city_id
                  LEFT JOIN districts d ON d.id = l.district_id
                  LEFT JOIN provinces p ON p.id = l.province_id
                  WHERE r.owner_id=?
                  ORDER BY r.created_at DESC';
          $q = db()->prepare($sql);
          if ($q) {
            $q->bind_param('i', $uid);
            if ($q->execute()) {
              $rs = $q->get_result();
              while ($row = $rs->fetch_assoc()) { $rooms[] = $row; }
            }
            $q->close();
          }
        ?>
        <?php foreach ($rooms as $r): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="rl-form-card h-100 room-card">
              <?php if (!empty($r['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($r['image_path']); ?>" class="card-img-top" alt="Room image">
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center placeholder-img">
                  <span>No image</span>
                </div>
              <?php endif; ?>
              <div class="rl-form-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <h5 class="mb-0" style="font-weight:800; color:var(--rl-text);"><?php echo htmlspecialchars($r['title'] ?: 'Untitled'); ?></h5>
                  <?php if (!empty($r['room_code'])): ?>
                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($r['room_code']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($r['status'])): ?>
                    <?php
                      $st = (string)$r['status'];
                      $map = [
                        'available' => 'success',
                        'rented' => 'warning',
                        'unavailable' => 'secondary',
                        'pending' => 'info',
                      ];
                      $cls = $map[$st] ?? 'secondary';
                    ?>
                    <span class="badge ms-2 bg-<?php echo $cls; ?> text-light"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st))); ?></span>
                  <?php endif; ?>
                </div>
                <div class="mb-2 small" style="font-weight:800; color:var(--rl-dark);">LKR <?php echo number_format((float)($r['price_per_day'] ?? 0), 2); ?> / day</div>
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
          <div class="col-12">
            <div class="rl-empty-state">
              <i class="bi bi-door-closed"></i>
              <p class="mb-3">You have not created any rooms yet.</p>
              <a href="room_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Room</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/room_read.js" defer></script>
</body>
</html>
