<?php
require_once __DIR__ . '/../../config/config.php';

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

$sql = "SELECT r.room_id, r.title, r.room_type, r.beds, r.price_per_day, r.status,
               (
                 SELECT ri.image_path FROM room_images ri
                 WHERE ri.room_id = r.room_id
                 ORDER BY ri.is_primary DESC, ri.image_id DESC
                 LIMIT 1
               ) AS image_path
        FROM rooms r
        LEFT JOIN locations l ON l.room_id = r.room_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        WHERE " . implode(' AND ', $conds) . "
        ORDER BY r.room_id DESC " . ($q!=='' || $province_id || $district_id || $city_id ? '' : 'LIMIT 8');

$stmt = db()->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$vals); }
$stmt->execute();
$res = $stmt->get_result();
$items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>
<section id="rooms-section" class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0"><i class=\"bi bi-door-open me-1\"></i>Rooms</h2>
    <a href="owner/room_management.php" class="btn btn-sm btn-outline-primary d-none">View all</a>
  </div>
  <div class="row g-3">
    <?php foreach ($items as $r): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border shadow-sm position-relative">
          <?php if (!empty($r['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($r['image_path'])): ?>
            <?php $src = $r['image_path']; if ($src && !preg_match('#^https?://#i', $src) && $src[0] !== '/') { $src = '/'.ltrim($src, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($src); ?>" class="w-100 h-100 object-fit-cover" alt="">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($r['title']); ?></h5>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars(ucfirst($r['room_type'])); ?> • Beds: <?php echo (int)$r['beds']; ?></div>
            <div class="mt-auto">
              <span class="fw-semibold">LKR <?php echo number_format((float)$r['price_per_day'], 2); ?>/day</span>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>"><i class="bi bi-eye me-1"></i>View</a>
              <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_room.php?id=<?php echo (int)$r['room_id']; ?>"><i class="bi bi-bag-plus me-1"></i>Rent</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <div class="col-12"><div class="alert alert-light border">No rooms found.</div></div>
    <?php endif; ?>
  </div>
</section>
