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
<style>
    /* ===========================
       SEARCH COMPONENT CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Search Container */
    .rl-search-shell {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border-radius: 16px;
      border: 2px solid #e2e8f0;
      box-shadow: 0 10px 40px rgba(0, 78, 152, 0.12);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .rl-search-shell::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #004E98 0%, #3A6EA5 50%, #FF6700 100%);
    }
    
    .rl-search-shell:hover {
      box-shadow: 0 15px 50px rgba(0, 78, 152, 0.18);
      transform: translateY(-2px);
    }
    
    /* Search Form Label */
    .rl-search-label {
      font-weight: 600;
      color: #1a202c;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
    }
    
    /* Input Group Styling */
    .rl-search-shell .input-group-text {
      background: linear-gradient(135deg, #004E98 0%, #3A6EA5 100%);
      border: none;
      color: #ffffff;
      border-radius: 10px 0 0 10px;
      padding: 0.75rem 1rem;
      font-size: 1.125rem;
    }
    
    .rl-search-shell .input-group .form-control {
      border-left: none;
      border-radius: 0 10px 10px 0;
      border: 2px solid #e2e8f0;
      border-left: none;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.2s ease;
    }
    
    .rl-search-shell .input-group .form-control:focus {
      border-color: #004E98;
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
      outline: none;
    }
    
    /* Form Controls */
    .rl-search-shell .form-control,
    .rl-search-shell .form-select {
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      color: #1a202c;
      background: #ffffff;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      font-weight: 500;
    }
    
    .rl-search-shell .form-control::placeholder {
      color: #a0aec0;
      font-weight: 400;
    }
    
    .rl-search-shell .form-control:focus,
    .rl-search-shell .form-select:focus {
      border-color: #004E98;
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
      outline: none;
      background: #ffffff;
    }
    
    .rl-search-shell .form-control:hover:not(:focus),
    .rl-search-shell .form-select:hover:not(:focus) {
      border-color: #cbd5e0;
    }
    
    /* Disabled Select Styling */
    .rl-search-shell .form-select:disabled {
      background: #f7fafc;
      color: #a0aec0;
      border-color: #e2e8f0;
      cursor: not-allowed;
      opacity: 0.7;
    }
    
    /* Field Wrapper for Spinner */
    .rl-field-wrap {
      position: relative;
    }
    
    .rl-field-spinner {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      z-index: 5;
    }
    
    .rl-field-spinner .spinner-border {
      width: 1.25rem;
      height: 1.25rem;
      border-width: 2px;
      color: #3A6EA5;
    }
    
    /* Search Button */
    .rl-btn-search {
      background: linear-gradient(135deg, #004E98 0%, #3A6EA5 100%);
      border: none;
      color: #ffffff;
      font-weight: 700;
      padding: 0.75rem 2rem;
      border-radius: 50rem;
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.25);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      font-size: 1rem;
      letter-spacing: 0.02em;
    }
    
    .rl-btn-search:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: #ffffff;
    }
    
    .rl-btn-search:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(0, 78, 152, 0.2);
    }
    
    .rl-btn-search i {
      font-size: 1.125rem;
    }
    
    /* Advanced Search Button */
    .rl-btn-advanced {
      background: #ffffff;
      border: 2px solid #e2e8f0;
      color: #4a5568;
      font-weight: 600;
      padding: 0.75rem 2rem;
      border-radius: 50rem;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      font-size: 1rem;
    }
    
    .rl-btn-advanced:hover {
      background: rgba(0, 78, 152, 0.05);
      border-color: #004E98;
      color: #004E98;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.15);
    }
    
    .rl-btn-advanced:active {
      transform: translateY(0);
    }
    
    .rl-btn-advanced i {
      font-size: 1rem;
    }
    
    /* Button Container */
    .rl-search-actions {
      display: flex;
      gap: 0.75rem;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 0.5rem;
    }
    
    /* Section Spacer */
    .rl-section-spacer {
      margin-top: 1.5rem;
    }
    
    @media (min-width: 768px) {
      .rl-section-spacer {
        margin-top: 2rem;
      }
    }
    
    /* Empty State */
    .rl-empty-state {
      border: 2px dashed #cbd5e0;
      background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
      border-radius: 16px;
      padding: 3rem 2rem;
      text-align: center;
      color: #718096;
    }
    
    .rl-empty-state i {
      font-size: 3rem;
      color: #a0aec0;
      margin-bottom: 1rem;
    }
    
    .rl-empty-state h4 {
      color: #4a5568;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    /* Animation for form appearance */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .rl-search-shell {
      animation: slideInUp 0.5s ease-out;
    }
    
    /* Focus Ring Enhancement */
    .rl-search-shell *:focus-visible {
      outline: 3px solid rgba(255, 103, 0, 0.4);
      outline-offset: 2px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 991px) {
      .rl-search-shell {
        border-radius: 14px;
      }
      
      .rl-search-shell .form-control,
      .rl-search-shell .form-select {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }
      
      .rl-search-shell .input-group-text {
        padding: 0.625rem 0.875rem;
        font-size: 1rem;
      }
    }
    
    @media (max-width: 767px) {
      .rl-search-shell {
        border-radius: 12px;
        padding: 1.25rem 1rem;
      }
      
      .rl-search-shell .form-control,
      .rl-search-shell .form-select {
        font-size: 0.875rem;
      }
      
      .rl-btn-search,
      .rl-btn-advanced {
        width: 100%;
        justify-content: center;
        padding: 0.875rem 1.5rem;
      }
      
      .rl-search-actions {
        flex-direction: column;
        gap: 0.5rem;
      }
    }
    
    @media (max-width: 575px) {
      .rl-search-shell {
        padding: 1rem 0.75rem;
      }
      
      .rl-search-shell::before {
        height: 3px;
      }
      
      .rl-btn-search,
      .rl-btn-advanced {
        font-size: 0.9375rem;
        padding: 0.75rem 1.25rem;
      }
    }
    
    /* Select Arrow Styling */
    .rl-search-shell .form-select {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23004E98' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 16px 12px;
      padding-right: 2.5rem;
    }
    
    /* Loading State */
    .rl-search-shell.loading {
      opacity: 0.7;
      pointer-events: none;
    }
</style>

<?php if ($isStandalone): ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Search</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
<?php endif; ?>
  <div class="mb-4 rl-search-shell p-3 p-md-4" role="search" aria-label="Property and room search">
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
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 rl-field-wrap">
            <label for="province_id" class="form-label visually-hidden">Province</label>
            <select class="form-select" name="province_id" id="province_id" aria-label="Province">
              <option value=""><?php echo $province_id ? 'Change province' : 'Province'; ?></option>
              <?php foreach ($provinces as $pv): ?>
                <option value="<?php echo (int)$pv['province_id']; ?>" <?php echo ($province_id === (int)$pv['province_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pv['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="rl-field-spinner d-none" id="spinner-province" aria-hidden="true">
              <span class="spinner-border spinner-border-sm" role="status"></span>
            </span>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 rl-field-wrap">
            <label for="district_id" class="form-label visually-hidden">District</label>
            <select class="form-select" name="district_id" id="district_id" aria-label="District" <?php echo $province_id ? '' : 'disabled aria-disabled="true"'; ?>>
              <option value=""><?php echo $province_id ? 'District' : 'Select province first'; ?></option>
              <?php foreach ($districts as $ds): ?>
                <option value="<?php echo (int)$ds['district_id']; ?>" <?php echo ($district_id === (int)$ds['district_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="rl-field-spinner d-none" id="spinner-district" aria-hidden="true">
              <span class="spinner-border spinner-border-sm" role="status" aria-label="Loading districts"></span>
            </span>
          </div>
          <div class="col-12 col-sm-6 col-md-6 col-lg-2 rl-field-wrap">
            <label for="city_id" class="form-label visually-hidden">City</label>
            <select class="form-select" name="city_id" id="city_id" aria-label="City" <?php echo $district_id ? '' : 'disabled aria-disabled="true"'; ?>>
              <option value=""><?php echo $district_id ? 'City' : 'Select district first'; ?></option>
              <?php foreach ($cities as $ct): ?>
                <option value="<?php echo (int)$ct['city_id']; ?>" <?php echo ($city_id === (int)$ct['city_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="rl-field-spinner d-none" id="spinner-city" aria-hidden="true">
              <span class="spinner-border spinner-border-sm" role="status" aria-label="Loading cities"></span>
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
            <div class="rl-search-actions">
              <button class="btn rl-btn-search" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search</button>
              <a class="btn rl-btn-advanced" href="<?php echo htmlspecialchars($base_url . '/public/includes/advance_search.php'); ?>">
                <i class="bi bi-funnel" aria-hidden="true"></i>Advanced Search
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

<script src="<?php echo htmlspecialchars($base_url . '/public/includes/js/search.js'); ?>" defer></script>

<?php if ($isStandalone): ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>
