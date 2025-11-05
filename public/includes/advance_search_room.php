<?php
require_once __DIR__ . '/../../config/config.php';

$q = trim($_GET['q'] ?? '');
$province_id = (int)($_GET['province_id'] ?? 0);
$district_id = (int)($_GET['district_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);

// Load dropdowns
$provinces = [];$districts=[];$cities=[];
$res = db()->query('SELECT id AS province_id, name_en AS name FROM provinces ORDER BY name_en');
if ($res) { while ($row=$res->fetch_assoc()) { $provinces[]=$row; } $res->free(); }
if ($province_id) {
  $st = db()->prepare('SELECT id AS district_id, name_en AS name FROM districts WHERE province_id=? ORDER BY name_en');
  $st->bind_param('i', $province_id); $st->execute(); $rr=$st->get_result();
  while ($row=$rr->fetch_assoc()) { $districts[]=$row; } $st->close();
}
if ($district_id) {
  $st = db()->prepare('SELECT id AS city_id, name_en AS name FROM cities WHERE district_id=? ORDER BY name_en');
  $st->bind_param('i', $district_id); $st->execute(); $rr=$st->get_result();
  while ($row=$rr->fetch_assoc()) { $cities[]=$row; } $st->close();
}

// Build search
$conds = ['r.status = "available"'];
$types = '';$vals=[];
if ($q !== '') {
  $like = '%' . $q . '%';
  $conds[] = '(r.title LIKE ? OR r.room_type LIKE ? OR pr.name_en LIKE ? OR d.name_en LIKE ? OR c.name_en LIKE ? OR l.address LIKE ? OR l.postal_code LIKE ?)';
  $types .= 'sssssss'; array_push($vals,$like,$like,$like,$like,$like,$like,$like);
}
if ($province_id) { $conds[] = 'l.province_id=?'; $types.='i'; $vals[]=$province_id; }
if ($district_id) { $conds[] = 'l.district_id=?'; $types.='i'; $vals[]=$district_id; }
if ($city_id) { $conds[] = 'l.city_id=?'; $types.='i'; $vals[]=$city_id; }

$sql = 'SELECT r.room_id, r.title, r.room_type, r.beds, r.price_per_day, r.status,
               (
                 SELECT ri.image_path FROM room_images ri
                 WHERE ri.room_id = r.room_id
                 ORDER BY ri.is_primary DESC, ri.image_id DESC
                 LIMIT 1
               ) AS image_path,
               pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name
        FROM rooms r
        LEFT JOIN locations l ON l.room_id = r.room_id
        LEFT JOIN provinces pr ON pr.id = l.province_id
        LEFT JOIN districts d ON d.id = l.district_id
        LEFT JOIN cities c ON c.id = l.city_id
        WHERE ' . implode(' AND ', $conds) . ' ORDER BY r.room_id DESC LIMIT 100';

$items = [];
$st = db()->prepare($sql);
if ($types!=='') { $st->bind_param($types, ...$vals); }
$st->execute(); $rs=$st->get_result();
while ($row=$rs->fetch_assoc()) { $items[]=$row; }
$st->close();

function money_lkr($n){ return 'LKR ' . number_format((float)$n,2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Advanced Room Search</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-funnel me-2"></i>Advanced Room Search</h1>
    <a href="<?php echo $base_url; ?>/" class="btn btn-outline-secondary btn-sm">Home</a>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-12 col-lg-4">
      <div class="input-group input-group-sm">
        <span class="input-group-text p-1 px-2"><i class="bi bi-search"></i></span>
        <input class="form-control form-control-sm" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search title, address...">
      </div>
    </div>
    <div class="col-6 col-lg-2">
      <select class="form-select form-select-sm" name="province_id">
        <option value="">Province</option>
        <?php foreach($provinces as $pv): ?>
          <option value="<?php echo (int)$pv['province_id']; ?>" <?php echo ($province_id===(int)$pv['province_id'])?'selected':''; ?>><?php echo htmlspecialchars($pv['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-lg-2">
      <select class="form-select form-select-sm" name="district_id">
        <option value="">District</option>
        <?php foreach($districts as $ds): ?>
          <option value="<?php echo (int)$ds['district_id']; ?>" <?php echo ($district_id===(int)$ds['district_id'])?'selected':''; ?>><?php echo htmlspecialchars($ds['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-lg-2">
      <select class="form-select form-select-sm" name="city_id">
        <option value="">City</option>
        <?php foreach($cities as $ct): ?>
          <option value="<?php echo (int)$ct['city_id']; ?>" <?php echo ($city_id===(int)$ct['city_id'])?'selected':''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-lg-2 d-grid">
      <button class="btn btn-primary btn-sm">Search</button>
    </div>
  </form>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    <?php foreach ($items as $r): ?>
      <div class="col">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($r['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($r['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($r['image_path'])): ?>
            <?php $img = $r['image_path']; if ($img && !preg_match('#^https?://#i',$img) && $img[0] !== '/') { $img = '/' . ltrim($img,'/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($img); ?>" class="w-100 h-100 object-fit-cover" alt="">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

