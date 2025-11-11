<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
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

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$rooms = [];
try {
  $sql = 'SELECT 
            rr.rent_id,
            rr.room_id,
            rr.customer_id,
            rr.checkin_date,
            rr.checkout_date,
            rr.guests,
            rr.meal_id,
            rr.price_per_night AS rent_price_per_night,
            rr.total_amount,
            rr.status AS rent_status,
            rr.created_at AS rent_created_at,
            r.room_code,
            r.title,
            r.room_type,
            r.beds,
            r.maximum_guests,
            (SELECT image_path FROM room_images WHERE room_id=r.room_id AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1) AS image_path,
            l.address, l.postal_code,
            c.name_en AS city_name,
            d.name_en AS district_name,
            p.name_en AS province_name,
            cu.name AS customer_name,
            rm.meal_name
          FROM room_rents rr
          INNER JOIN rooms r ON r.room_id = rr.room_id AND r.owner_id = ?
          LEFT JOIN users cu ON cu.user_id = rr.customer_id
          LEFT JOIN room_locations l ON l.room_id = r.room_id
          LEFT JOIN cities c ON c.id = l.city_id
          LEFT JOIN districts d ON d.id = l.district_id
          LEFT JOIN provinces p ON p.id = l.province_id
          LEFT JOIN room_meals rm ON rm.room_id = rr.room_id AND rm.meal_id = rr.meal_id
          ORDER BY rr.rent_id DESC';
  $q = db()->prepare($sql);
  $q->bind_param('i', $uid);
  $q->execute();
  $rs = $q->get_result();
  while ($row = $rs->fetch_assoc()) { $rooms[] = $row; }
  $q->close();
} catch (Throwable $e) {
  $rooms = [];
}

function loc_line($r) {
  $parts = [];
  if (!empty($r['city_name'])) $parts[] = (string)$r['city_name'];
  if (!empty($r['district_name'])) $parts[] = (string)$r['district_name'];
  if (!empty($r['province_name'])) $parts[] = (string)$r['province_name'];
  return implode(', ', $parts);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.25rem 1.75rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.5rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1rem 1.25rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    .rent-card { position:relative; transition:all .3s cubic-bezier(.4,0,.2,1); }
    .rent-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .25s ease; }
    .rent-card:hover { transform:translateY(-6px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent) !important; }
    .rent-card:hover::before { opacity:1; }
    .card-img-top { object-fit:cover; height: 200px; }
    .placeholder-img { background:linear-gradient(135deg,#eef2f7 0%, #e2e8f0 100%); height:200px; }

    .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

    /* Brand utility badges and price to surface all colors */
    .rl-badge-accent { background: linear-gradient(135deg, var(--rl-accent) 0%, var(--rl-primary) 100%); color: var(--rl-white); padding: .35rem .7rem; border-radius: 20px; font-weight:700; font-size:.8rem; letter-spacing:.3px; }
    .rl-badge-dark { background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8a33 100%); color: var(--rl-white); padding: .35rem .7rem; border-radius: 20px; font-weight:700; font-size:.8rem; letter-spacing:.3px; }
    .rl-price { font-weight:800; color: var(--rl-dark); }

    @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } .card-img-top,.placeholder-img{ height:180px; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-check-circle"></i> Rented Rooms</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php if (!$rooms): ?>
    <div class="rl-form-card">
      <div class="rl-form-body">
        <div class="rl-empty-state">
          <i class="bi bi-door-closed"></i>
          <p class="mb-3">No rental records found for your rooms.</p>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="rl-form-card">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-card-list"></i> Your Rented Rooms</h2></div>
      <div class="rl-form-body">
        <div class="row g-3">
      <?php foreach ($rooms as $r): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="rl-form-card h-100 rent-card">
            <?php if (!empty($r['image_path'])): ?>
              <img src="<?php echo htmlspecialchars($r['image_path']); ?>" class="card-img-top" alt="Room image">
            <?php else: ?>
              <div class="placeholder-img"></div>
            <?php endif; ?>
            <div class="rl-form-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="fw-bold" style="font-size:1.05rem; color:var(--rl-text); "><?php echo htmlspecialchars($r['title'] ?: 'Untitled'); ?></div>
                  <div class="text-muted small"><?php echo htmlspecialchars((string)$r['room_code']); ?></div>
                </div>
                <?php $rst = (string)($r['rent_status'] ?? ''); $rcls = ['booked'=>'primary','checked_in'=>'warning','checked_out'=>'success','cancelled'=>'secondary'][$rst] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $rcls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $rst ?: 'booked'))); ?></span>
              </div>
              <div class="mb-3">
                <div class="small d-flex align-items-center gap-2"><strong>Rent ID:</strong> <span class="rl-badge-accent">#<?php echo (int)$r['rent_id']; ?></span></div>
                <div class="small"><strong>Customer:</strong> <?php echo htmlspecialchars((string)($r['customer_name'] ?? '')); ?></div>
                <div class="small"><strong>Check-in:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['checkin_date']))); ?></div>
                <div class="small"><strong>Check-out:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['checkout_date']))); ?></div>
                <div class="small"><strong>Guests:</strong> <?php echo (int)($r['guests'] ?? 0); ?></div>
                <div class="small"><strong>Meal:</strong> <?php echo htmlspecialchars((string)($r['meal_name'] ?? 'No meal')); ?></div>
                <div class="small"><strong>Price/Night:</strong> LKR <?php echo number_format((float)($r['rent_price_per_night'] ?? 0), 2); ?></div>
                <div class="small"><strong>Total:</strong> <span class="rl-price">LKR <?php echo number_format((float)($r['total_amount'] ?? 0), 2); ?></span></div>
              </div>
              <?php $parts = []; if (!empty($r['city_name'])) $parts[] = (string)$r['city_name']; if (!empty($r['district_name'])) $parts[] = (string)$r['district_name']; if (!empty($r['province_name'])) $parts[] = (string)$r['province_name']; $loc = implode(', ', $parts); ?>
              <?php if ($loc || !empty($r['postal_code'])): ?>
                <div class="text-muted small mb-3">
                  <i class="bi bi-geo-alt"></i>
                  <?php echo htmlspecialchars($loc); ?><?php echo ($loc && !empty($r['postal_code'])) ? ' • ' : ''; ?><?php echo !empty($r['postal_code']) ? htmlspecialchars((string)$r['postal_code']) : ''; ?>
                </div>
              <?php endif; ?>
              <div class="mt-auto d-flex gap-2 flex-wrap">
                <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>" class="btn btn-outline-secondary btn-sm">View Room</a>
                <a href="<?php echo $base_url; ?>/public/includes/my_rentals.php" class="btn btn-outline-primary btn-sm">My Rentals</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
