<?php
require_once __DIR__ . '/../../config/config.php';
$isStandalone = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'search.php');

$q = trim($_GET['q'] ?? '');
$province_id = (int)($_GET['province_id'] ?? 0);
$district_id = (int)($_GET['district_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);
// Results are rendered by property.php and room.php includes; no server-side arrays needed here

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

// Removed old server-rendered results (now rendered via property.php and room.php)
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
      <div class="col-12">
        <style>
          .search-eq .form-control,
          .search-eq .form-select,
          .search-eq .btn,
          .search-eq .input-group-text { height: 40px; }
          .search-eq .form-control,
          .search-eq .form-select { padding-top: .375rem; padding-bottom: .375rem; font-size: .95rem; }
          .search-eq .input-group-text { padding-top: .375rem; padding-bottom: .375rem; }
        </style>
        <div class="row g-2 align-items-end search-eq">
          <div class="col-12 col-md-6 col-lg-4">
            <div class="input-group">
              <span class="input-group-text p-1 px-2"><i class="bi bi-search"></i></span>
              <input class="form-control" type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search properties or rooms..." aria-label="Search" autocomplete="off" autofocus>
            </div>
          </div>
          <div class="col-12 col-md-6 col-lg-2">
            <select class="form-select" name="province_id" id="province_id">
              <option value="">Province</option>
              <?php foreach ($provinces as $pv): ?>
                <option value="<?php echo (int)$pv['province_id']; ?>" <?php echo ($province_id === (int)$pv['province_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pv['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-2">
            <select class="form-select" name="district_id" id="district_id" <?php echo $province_id ? '' : 'disabled'; ?>>
              <option value="">District</option>
              <?php foreach ($districts as $ds): ?>
                <option value="<?php echo (int)$ds['district_id']; ?>" <?php echo ($district_id === (int)$ds['district_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-2">
            <select class="form-select" name="city_id" id="city_id" <?php echo $district_id ? '' : 'disabled'; ?>>
              <option value="">City</option>
              <?php foreach ($cities as $ct): ?>
                <option value="<?php echo (int)$ct['city_id']; ?>" <?php echo ($city_id === (int)$ct['city_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-1">
            <?php $scope = in_array(($_GET['scope'] ?? 'all'), ['all','properties','rooms'], true) ? $_GET['scope'] : 'all'; ?>
            <select class="form-select" name="scope" id="scope">
              <option value="all" <?php echo $scope==='all'?'selected':''; ?>>All</option>
              <option value="properties" <?php echo $scope==='properties'?'selected':''; ?>>Properties</option>
              <option value="rooms" <?php echo $scope==='rooms'?'selected':''; ?>>Rooms</option>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-1 d-grid">
            <button class="btn btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i></button>
          </div>
        </div>
      </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Results are rendered by property.php and room.php sections on the page -->
  <?php if ($isStandalone): ?>
    <?php include __DIR__ . '/property.php'; ?>
    <?php include __DIR__ . '/room.php'; ?>
  <?php endif; ?>
  </div>
<script>
  (function(){
    const form = document.getElementById('search-form');
    if (!form) return;
    const province = document.getElementById('province_id');
    const district = document.getElementById('district_id');
    const city = document.getElementById('city_id');
    const scopeSel = document.getElementById('scope');

    function params() {
      const p = new URLSearchParams();
      const qInput = form.querySelector('input[name="q"]');
      const q = qInput ? qInput.value.trim() : '';
      if (q) p.set('q', q);
      if (province && province.value) p.set('province_id', province.value);
      if (district && district.value) p.set('district_id', district.value);
      if (city && city.value) p.set('city_id', city.value);
      if (scopeSel && scopeSel.value) p.set('scope', scopeSel.value);
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

    function currentScope() {
      const v = (scopeSel && scopeSel.value) ? scopeSel.value : 'all';
      return v === 'properties' || v === 'rooms' ? v : 'all';
    }

    async function fetchResults() {
      const p = params();
      const base = new URL(form.action, window.location.origin);
      // property.php URL
      const propUrl = new URL('property.php', base);
      propUrl.search = p.toString();
      // room.php URL
      const roomUrl = new URL('room.php', base);
      roomUrl.search = p.toString();
      const tasks = [];
      // Toggle visibility before fetching based on scope
      const propsSection = document.getElementById('properties-section');
      const roomsSection = document.getElementById('rooms-section');
      const scope = currentScope();
      if (propsSection) propsSection.classList.toggle('d-none', scope === 'rooms');
      if (roomsSection) roomsSection.classList.toggle('d-none', scope === 'properties');
      if (scope !== 'rooms') tasks.push(replaceSection('properties-section', propUrl.toString()));
      if (scope !== 'properties') tasks.push(replaceSection('rooms-section', roomUrl.toString()));
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
    if (scopeSel) scopeSel.addEventListener('change', () => { fetchResults(); });
  })();
  // Trigger initial fetch to honor scope selection and refresh sections
  document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('search-form');
    if (form) { form.dispatchEvent(new Event('submit', { cancelable: true })); }
  });
  </script>
<?php if ($isStandalone): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>
