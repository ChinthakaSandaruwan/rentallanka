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
  <style>
    .search-shell { background: #fff; border-radius: .75rem; border: 1px solid rgba(0,0,0,.075); }
    .section-spacer { margin-top: 1.25rem; }
    @media (min-width: 768px) { .section-spacer { margin-top: 1.5rem; } }
    .field-wrap { position: relative; }
    .field-spinner { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
    .btn-pill { border-radius: 50rem; }
    .form-control:focus, .form-select:focus { box-shadow: 0 0 0 .25rem rgba(13,110,253,.15); }
    .empty-state { border: 1px dashed rgba(0,0,0,.15); background: #f8f9fa; border-radius: .5rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
<?php endif; ?>
  <div class="mb-4 search-shell p-3 p-md-4" role="search" aria-label="Property and room search">
    <div class="row justify-content-center">
      <div class="col-12 col-md-11 col-lg-10">
        <form id="search-form" class="row gy-3 gx-2 align-items-end" method="get" action="<?php echo htmlspecialchars($base_url . '/public/includes/search.php'); ?>">
          <div class="col-12 col-md-6 col-lg-4">
            <label for="q" class="form-label visually-hidden">Keyword</label>
            <div class="input-group">
              <span class="input-group-text" id="q-addon"><i class="bi bi-search" aria-hidden="true"></i></span>
              <input class="form-control" id="q" type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search properties or rooms..." aria-label="Search keywords" aria-describedby="q-addon" autocomplete="off">
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 field-wrap">
            <label for="province_id" class="form-label visually-hidden">Province</label>
            <select class="form-select" name="province_id" id="province_id" aria-label="Province">
              <option value=""><?php echo $province_id ? 'Change province' : 'Province'; ?></option>
              <?php foreach ($provinces as $pv): ?>
                <option value="<?php echo (int)$pv['province_id']; ?>" <?php echo ($province_id === (int)$pv['province_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pv['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-spinner d-none" id="spinner-province" aria-hidden="true">
              <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
            </span>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 field-wrap">
            <label for="district_id" class="form-label visually-hidden">District</label>
            <select class="form-select" name="district_id" id="district_id" aria-label="District" <?php echo $province_id ? '' : 'disabled aria-disabled="true"'; ?>>
              <option value=""><?php echo $province_id ? 'District' : 'Select province first'; ?></option>
              <?php foreach ($districts as $ds): ?>
                <option value="<?php echo (int)$ds['district_id']; ?>" <?php echo ($district_id === (int)$ds['district_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-spinner d-none" id="spinner-district" aria-hidden="true">
              <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-label="Loading districts"></span>
            </span>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 field-wrap">
            <label for="city_id" class="form-label visually-hidden">City</label>
            <select class="form-select" name="city_id" id="city_id" aria-label="City" <?php echo $district_id ? '' : 'disabled aria-disabled="true"'; ?>>
              <option value=""><?php echo $district_id ? 'City' : 'Select district first'; ?></option>
              <?php foreach ($cities as $ct): ?>
                <option value="<?php echo (int)$ct['city_id']; ?>" <?php echo ($city_id === (int)$ct['city_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-spinner d-none" id="spinner-city" aria-hidden="true">
              <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-label="Loading cities"></span>
            </span>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2">
            <?php $scope_raw = strtolower((string)($_GET['scope'] ?? 'all')); $scope = in_array($scope_raw, ['all','properties','rooms'], true) ? $scope_raw : 'all'; ?>
            <label for="scope" class="form-label visually-hidden">Scope</label>
            <select class="form-select" name="scope" id="scope" aria-label="Result type">
              <option value="all" <?php echo $scope==='all'?'selected':''; ?>>All</option>
              <option value="properties" <?php echo $scope==='properties'?'selected':''; ?>>Properties</option>
              <option value="rooms" <?php echo $scope==='rooms'?'selected':''; ?>>Rooms</option>
            </select>
          </div>
          <div class="col-12">
            <div class="d-grid d-md-flex gap-2 justify-content-center">
              <button class="btn btn-primary btn-pill px-4" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
              <a class="btn btn-outline-secondary px-4" href="<?php echo htmlspecialchars($base_url . '/public/includes/advance_search.php'); ?>">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i> Advanced Search
              </a>
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
  <script src="<?php echo htmlspecialchars($base_url . '/public/includes/js/search.js'); ?>" defer></script>
  <?php if ($isStandalone): ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>
