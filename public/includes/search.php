<?php
require_once __DIR__ . '/../../config/config.php';
$isStandalone = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'search.php');

$q = trim($_GET['q'] ?? '');
$province_id = (int)($_GET['province_id'] ?? 0);
$district_id = (int)($_GET['district_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);
$items_props = [];
$items_rooms = [];

// Load dropdown options
$provinces = [];
$districts = [];
$cities = [];
$res = db()->query('SELECT id AS province_id, name_en AS name FROM provinces ORDER BY name_en');
if ($res) { while ($row = $res->fetch_assoc()) { $provinces[] = $row; } $res->free(); }
if ($province_id) {
  $st = db()->prepare('SELECT id AS district_id, name_en AS name FROM districts WHERE province_id = ? ORDER BY name_en');
  $st->bind_param('i', $province_id);
  $st->execute();
  $r = $st->get_result();
  while ($row = $r->fetch_assoc()) { $districts[] = $row; }
  $st->close();
}
if ($district_id) {
  $st = db()->prepare('SELECT id AS city_id, name_en AS name FROM cities WHERE district_id = ? ORDER BY name_en');
  $st->bind_param('i', $district_id);
  $st->execute();
  $r = $st->get_result();
  while ($row = $r->fetch_assoc()) { $cities[] = $row; }
  $st->close();
}

// AJAX endpoints for cascading selects
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'districts') {
  if (!headers_sent()) { header('Content-Type: application/json'); }
  $pid = (int)($_GET['province_id'] ?? 0);
  $data = [];
  if ($pid) {
    $st = db()->prepare('SELECT id AS district_id, name_en AS name FROM districts WHERE province_id = ? ORDER BY name_en');
    $st->bind_param('i', $pid);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) { $data[] = $row; }
    $st->close();
  }
  echo json_encode($data);
  exit;
}
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'cities') {
  if (!headers_sent()) { header('Content-Type: application/json'); }
  $did = (int)($_GET['district_id'] ?? 0);
  $data = [];
  if ($did) {
    $st = db()->prepare('SELECT id AS city_id, name_en AS name FROM cities WHERE district_id = ? ORDER BY name_en');
    $st->bind_param('i', $did);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) { $data[] = $row; }
    $st->close();
  }
  echo json_encode($data);
  exit;
}

if ($q !== '' || $province_id || $district_id || $city_id) {
  $like = '%' . $q . '%';

  // Build dynamic filters shared by both queries
  $locWhere = [];
  $locParams = '';
  $locBind = [];
  if ($province_id) { $locWhere[] = 'l.province_id = ?'; $locParams .= 'i'; $locBind[] = $province_id; }
  if ($district_id) { $locWhere[] = 'l.district_id = ?'; $locParams .= 'i'; $locBind[] = $district_id; }
  if ($city_id) { $locWhere[] = 'l.city_id = ?'; $locParams .= 'i'; $locBind[] = $city_id; }

  // Properties (available)
  $conds = ['p.status = "available"'];
  $types = '';
  $vals = [];
  if ($q !== '') {
    $conds[] = '(p.title LIKE ? OR p.description LIKE ? OR pr.name_en LIKE ? OR d.name_en LIKE ? OR c.name_en LIKE ? OR l.address LIKE ? OR l.postal_code LIKE ?)';
    $types .= 'sssssss';
    array_push($vals, $like, $like, $like, $like, $like, $like, $like);
  }
  if ($locWhere) {
    $conds[] = '(' . implode(' AND ', $locWhere) . ')';
    $types .= $locParams;
    foreach ($locBind as $v) { $vals[] = $v; }
  }
  $sqlP = 'SELECT p.property_id, p.title, p.description, p.image, p.price_per_month, p.property_type, p.status,
                  pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name, l.address, l.postal_code
           FROM properties p
           LEFT JOIN locations l ON l.property_id = p.property_id
           LEFT JOIN provinces pr ON pr.id = l.province_id
           LEFT JOIN districts d ON d.id = l.district_id
           LEFT JOIN cities c ON c.id = l.city_id
           WHERE ' . implode(' AND ', $conds) . ' ORDER BY p.property_id DESC LIMIT 50';
  $sp = db()->prepare($sqlP);
  if ($types !== '') { $sp->bind_param($types, ...$vals); }
  $sp->execute();
  $rp = $sp->get_result();
  while ($row = $rp->fetch_assoc()) { $items_props[] = $row; }
  $sp->close();

  // Rooms (available)
  $conds = ['r.status = "available"'];
  $types = '';
  $vals = [];
  if ($q !== '') {
    $conds[] = '(r.title LIKE ? OR r.room_type LIKE ? OR pr.name_en LIKE ? OR d.name_en LIKE ? OR c.name_en LIKE ? OR l.address LIKE ? OR l.postal_code LIKE ?)';
    $types .= 'sssssss';
    array_push($vals, $like, $like, $like, $like, $like, $like, $like);
  }
  if ($locWhere) {
    $conds[] = '(' . implode(' AND ', $locWhere) . ')';
    $types .= $locParams;
    foreach ($locBind as $v) { $vals[] = $v; }
  }
  $sqlR = 'SELECT r.room_id, r.title, r.room_type, r.beds, r.status, r.price_per_day,
                  pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name, l.address, l.postal_code,
                  ri.image_path AS image
           FROM rooms r
           LEFT JOIN locations l ON l.room_id = r.room_id
           LEFT JOIN provinces pr ON pr.id = l.province_id
           LEFT JOIN districts d ON d.id = l.district_id
           LEFT JOIN cities c ON c.id = l.city_id
           LEFT JOIN room_images ri ON ri.room_id = r.room_id AND ri.is_primary = 1
           WHERE ' . implode(' AND ', $conds) . ' ORDER BY r.room_id DESC LIMIT 50';
  $sr = db()->prepare($sqlR);
  if ($types !== '') { $sr->bind_param($types, ...$vals); }
  $sr->execute();
  $rr = $sr->get_result();
  while ($row = $rr->fetch_assoc()) { $items_rooms[] = $row; }
  $sr->close();
}

function money_lkr($n) { return 'LKR ' . number_format((float)$n, 2); }
function status_badge_class($s) {
  $s = strtolower(trim((string)$s));
  if ($s === 'available') return 'bg-success';
  if ($s === 'pending') return 'bg-warning text-dark';
  if ($s === 'rented' || $s === 'unavailable') return 'bg-danger';
  return 'bg-secondary';
}

function render_results($items_props, $items_rooms, $q, $province_id, $district_id, $city_id, $base_url) {
  ?>
  <?php if ($q === '' && !$province_id && !$district_id && !$city_id): ?>
  <?php else: ?>
    <h2 class="h5 mb-3"><i class="bi bi-building me-1"></i>Properties</h2>
    <div class="row g-3 mb-4">
      <?php foreach ($items_props as $p): ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="card h-100 border shadow-sm">
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
              <div class="mb-2"><span class="badge text-uppercase small <?php echo status_badge_class($p['status']); ?>"><?php echo htmlspecialchars($p['status']); ?></span></div>
              <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($p['price_per_month']); ?>/month</div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$items_props): ?>
        <div class="col-12"><div class="alert alert-light border">No properties found.</div></div>
      <?php endif; ?>
    </div>

    <h2 class="h5 mb-3"><i class="bi bi-door-open me-1"></i>Rooms</h2>
    <div class="row g-3">
      <?php foreach ($items_rooms as $r): ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="card h-100 border shadow-sm">
            <?php if (!empty($r['image'])): ?>
              <?php $img = $r['image']; if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); } ?>
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
              <div class="mb-2"><span class="badge text-uppercase small <?php echo status_badge_class($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></div>
              <div class="mt-auto fw-bold text-primary"><?php echo money_lkr($r['price_per_day']); ?>/day</div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$r['room_id']; ?>">View</a>
                <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_room.php?id=<?php echo (int)$r['room_id']; ?>">Rent</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$items_rooms): ?>
        <div class="col-12"><div class="alert alert-light border">No rooms found.</div></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php
}

if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'results') {
  if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
  ob_start();
  render_results($items_props, $items_rooms, $q, $province_id, $district_id, $city_id, $base_url);
  echo ob_get_clean();
  exit;
}
?>
<?php if ($isStandalone): ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Search</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
<?php endif; ?>
  <div class="mb-4">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <form id="search-form" class="row g-2 align-items-end" method="get" action="<?php echo htmlspecialchars($base_url . '/public/includes/search.php'); ?>">
      <div class="col-12 col-lg-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text p-1 px-2"><i class="bi bi-search"></i></span>
          <input class="form-control form-control-sm py-1" type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search properties or rooms..." aria-label="Search" autocomplete="off" autofocus>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <select class="form-select form-select-sm" name="province_id" id="province_id">
          <option value="">Province</option>
          <?php foreach ($provinces as $pv): ?>
            <option value="<?php echo (int)$pv['province_id']; ?>" <?php echo ($province_id === (int)$pv['province_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pv['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <select class="form-select form-select-sm" name="district_id" id="district_id" <?php echo $province_id ? '' : 'disabled'; ?>>
          <option value="">District</option>
          <?php foreach ($districts as $ds): ?>
            <option value="<?php echo (int)$ds['district_id']; ?>" <?php echo ($district_id === (int)$ds['district_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <select class="form-select form-select-sm" name="city_id" id="city_id" <?php echo $district_id ? '' : 'disabled'; ?>>
          <option value="">City</option>
          <?php foreach ($cities as $ct): ?>
            <option value="<?php echo (int)$ct['city_id']; ?>" <?php echo ($city_id === (int)$ct['city_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-2">
        <div class="d-grid gap-2">
          <div class="d-flex gap-3 small align-items-center">
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" id="search_props" name="search_props" value="1" checked>
              <label class="form-check-label" for="search_props">Properties</label>
            </div>
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" id="search_rooms" name="search_rooms" value="1" checked>
              <label class="form-check-label" for="search_rooms">Rooms</label>
            </div>
          </div>
          <button class="btn btn-primary btn-sm rounded-pill px-4 w-100" type="submit"><i class="bi bi-search me-1"></i>Search</button>
        </div>
      </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Results are rendered by property.php and room.php sections on the page -->
  </div>
<script>
  (function(){
    const form = document.getElementById('search-form');
    if (!form) return;
    const province = document.getElementById('province_id');
    const district = document.getElementById('district_id');
    const city = document.getElementById('city_id');
    const cbProps = document.getElementById('search_props');
    const cbRooms = document.getElementById('search_rooms');

    function params() {
      const p = new URLSearchParams();
      const qInput = form.querySelector('input[name="q"]');
      const q = qInput ? qInput.value.trim() : '';
      if (q) p.set('q', q);
      if (province && province.value) p.set('province_id', province.value);
      if (district && district.value) p.set('district_id', district.value);
      if (city && city.value) p.set('city_id', city.value);
      if (cbProps && cbProps.checked) p.set('search_props', '1');
      if (cbRooms && cbRooms.checked) p.set('search_rooms', '1');
      return p;
    }

    async function replaceSection(sectionId, url) {
      const target = document.getElementById(sectionId);
      if (!target) return;
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await res.text();
      // Replace entire section markup coming from the include
      target.outerHTML = html;
    }

    function ensureAtLeastOneScope() {
      if (!cbProps || !cbRooms) return;
      if (!cbProps.checked && !cbRooms.checked) {
        // Re-enable Properties by default if both are off
        cbProps.checked = true;
      }
    }

    async function fetchResults() {
      ensureAtLeastOneScope();
      const p = params();
      const base = new URL(form.action, window.location.origin);
      // property.php URL
      const propUrl = new URL('property.php', base);
      propUrl.search = p.toString();
      // room.php URL
      const roomUrl = new URL('room.php', base);
      roomUrl.search = p.toString();
      const tasks = [];
      // Toggle visibility before fetching
      const propsSection = document.getElementById('properties-section');
      const roomsSection = document.getElementById('rooms-section');
      if (propsSection) propsSection.classList.toggle('d-none', cbProps && !cbProps.checked);
      if (roomsSection) roomsSection.classList.toggle('d-none', cbRooms && !cbRooms.checked);
      if (cbProps && cbProps.checked) tasks.push(replaceSection('properties-section', propUrl.toString()));
      if (cbRooms && cbRooms.checked) tasks.push(replaceSection('rooms-section', roomUrl.toString()));
      await Promise.all(tasks);
    }

    async function fetchDistricts() {
      if (!province || !district) return;
      const p = new URLSearchParams({ ajax: '1', action: 'districts', province_id: province.value || '' });
      const url = `${form.action}?${p.toString()}`;
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      district.innerHTML = '<option value="">District</option>' + data.map(d => `<option value="${d.district_id}">${d.name}</option>`).join('');
      district.disabled = !province.value;
    }

    async function fetchCities() {
      if (!district || !city) return;
      const p = new URLSearchParams({ ajax: '1', action: 'cities', district_id: district.value || '' });
      const url = `${form.action}?${p.toString()}`;
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      city.innerHTML = '<option value="">City</option>' + data.map(c => `<option value="${c.city_id}">${c.name}</option>`).join('');
      city.disabled = !district.value;
    }

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      fetchResults();
    });

    if (province) {
      province.addEventListener('change', async function() {
        if (district) district.value = '';
        if (city) { city.value = ''; city.disabled = true; }
        await fetchDistricts();
        await fetchResults();
      });
    }
    if (district) {
      district.addEventListener('change', async function() {
        if (city) city.value = '';
        await fetchCities();
        await fetchResults();
      });
    }
    if (city) {
      city.addEventListener('change', function() { fetchResults(); });
    }
    if (cbProps) cbProps.addEventListener('change', () => { ensureAtLeastOneScope(); fetchResults(); });
    if (cbRooms) cbRooms.addEventListener('change', () => { ensureAtLeastOneScope(); fetchResults(); });
  })();
  </script>
<?php if ($isStandalone): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>
