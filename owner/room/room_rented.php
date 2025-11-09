<?php
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
            rr.price_per_day AS rent_price_per_day,
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
          LEFT JOIN locations l ON l.room_id = r.room_id
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
  <title>Rented Rooms</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-check-circle me-2"></i>Rented Rooms</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if (!$rooms): ?>
    <div class="alert alert-info">No rental records found for your rooms.</div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
      <?php foreach ($rooms as $r): ?>
        <div class="col">
          <div class="card h-100">
            <?php if (!empty($r['image_path'])): ?>
              <img src="<?php echo htmlspecialchars($r['image_path']); ?>" class="card-img-top" alt="Room image" style="height: 160px; object-fit: cover;">
            <?php else: ?>
              <div class="bg-light" style="height:160px;"></div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($r['title'] ?: 'Untitled'); ?></div>
                  <div class="text-muted small"><?php echo htmlspecialchars((string)$r['room_code']); ?></div>
                </div>
                <?php $rst = (string)($r['rent_status'] ?? ''); $rcls = ['booked'=>'primary','checked_in'=>'warning','checked_out'=>'success','cancelled'=>'secondary'][$rst] ?? 'secondary'; ?>
                <span class="badge bg-<?php echo $rcls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $rst ?: 'booked'))); ?></span>
              </div>
              <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item px-0 py-1"><strong>Rent ID:</strong> #<?php echo (int)$r['rent_id']; ?></li>
                <li class="list-group-item px-0 py-1"><strong>Customer:</strong> <?php echo htmlspecialchars((string)($r['customer_name'] ?? '')); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Check-in:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['checkin_date']))); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Check-out:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['checkout_date']))); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Guests:</strong> <?php echo (int)($r['guests'] ?? 0); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Meal:</strong> <?php echo htmlspecialchars((string)($r['meal_name'] ?? 'No meal')); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Price/Day:</strong> LKR <?php echo number_format((float)($r['rent_price_per_day'] ?? 0), 2); ?></li>
                <li class="list-group-item px-0 py-1"><strong>Total:</strong> LKR <?php echo number_format((float)($r['total_amount'] ?? 0), 2); ?></li>
              </ul>
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
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
