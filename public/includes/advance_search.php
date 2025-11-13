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
require_once __DIR__ . '/../../config/cache.php';

$type = ($_GET['type'] ?? 'property');
if ($type !== 'room') { $type = 'property'; }
$page = max(1, (int)($_GET['page'] ?? 1));
// support `per_page=all` to load all results on a single page
$per_page_raw = $_GET['per_page'] ?? 12;
$per_page_all = ((string)$per_page_raw === 'all');
$per_page = $per_page_all ? 12 : max(1, min(30, (int)$per_page_raw));
if ($per_page_all) { $page = 1; }
$offset = ($page - 1) * $per_page;

// current user (for potential user-specific rendering later)
$uid = (int)($_SESSION['user']['user_id'] ?? 0);

$kw = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'newest');

function build_property_query(array $q, string $kw, string $sort, int $per_page, int $offset, bool $no_limit=false): array {
  $where = [];$args = [];$types = '';$order = '';
  $uid = (int)($GLOBALS['uid'] ?? 0);
  if ($kw !== '') {
    $like = '%' . $kw . '%';
    $where[] = '(properties.title LIKE ? OR properties.description LIKE ?)';
    $args[] = $like; $args[] = $like; $types .= 'ss';
  }
  if (!empty($q['property_type'])) { $where[] = 'properties.property_type = ?'; $args[]=$q['property_type']; $types.='s'; }
  if ($q['price_min'] !== null) { $where[] = 'properties.price_per_month >= ?'; $args[]=(float)$q['price_min']; $types.='d'; }
  if ($q['price_max'] !== null) { $where[] = 'properties.price_per_month <= ?'; $args[]=(float)$q['price_max']; $types.='d'; }
  if ($q['bedrooms'] !== null) { $where[] = 'properties.bedrooms >= ?'; $args[]=(int)$q['bedrooms']; $types.='i'; }
  if ($q['bathrooms'] !== null) { $where[] = 'properties.bathrooms >= ?'; $args[]=(int)$q['bathrooms']; $types.='i'; }
  if ($q['living_rooms'] !== null) { $where[] = 'properties.living_rooms >= ?'; $args[]=(int)$q['living_rooms']; $types.='i'; }
  foreach (['garden','gym','pool','kitchen','parking'] as $b) { if ($q[$b] !== null) { $where[] = 'properties.' . $b.' = ?'; $args[]=(int)$q[$b]; $types.='i'; } }
  if (!empty($q['status'])) { $where[] = 'properties.status = ?'; $args[]=$q['status']; $types.='s'; }
  if ($q['sqft_min'] !== null) { $where[] = 'properties.sqft >= ?'; $args[]=(float)$q['sqft_min']; $types.='d'; }
  if ($q['sqft_max'] !== null) { $where[] = 'properties.sqft <= ?'; $args[]=(float)$q['sqft_max']; $types.='d'; }
  
  $where_sql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
  $limit_clause = $no_limit ? '' : ' LIMIT ? OFFSET ?';
  if ($sort === 'price_asc') { $order = 'ORDER BY properties.price_per_month ASC, properties.property_id DESC'; }
  elseif ($sort === 'price_desc') { $order = 'ORDER BY properties.price_per_month DESC, properties.property_id DESC'; }
  else { $order = 'ORDER BY properties.created_at DESC, properties.property_id DESC'; }
  $select_w = $uid>0 ? ', IF(w.wishlist_id IS NULL, 0, 1) AS in_wishlist' : ', 0 AS in_wishlist';
  $join_w = $uid>0 ? ' LEFT JOIN wishlist w ON w.property_id = properties.property_id AND w.customer_id = ?' : '';
  $sql = 'SELECT properties.property_id, properties.property_code, properties.title, properties.description, properties.image, properties.price_per_month, properties.bedrooms, properties.bathrooms, properties.living_rooms, properties.garden, properties.gym, properties.pool, properties.kitchen, properties.parking, properties.property_type, properties.status, properties.created_at'
       . $select_w
       . ' FROM properties'
       . $join_w
       . ' ' . $where_sql . ' ' . $order . $limit_clause;
  $count_sql = 'SELECT COUNT(1) AS c FROM properties '.$where_sql;
  $args_join = $uid>0 ? [$uid] : [];
  $types_join = $uid>0 ? 'i' : '';
  $args_all = array_merge($args_join, $args);
  $types_all = $types_join . $types;
  if ($limit_clause === '') { $args_limit = []; $types_limit = ''; }
  else { $args_limit = ($limit_clause === ' LIMIT ?') ? [$per_page] : [$per_page, $offset]; $types_limit = ($limit_clause === ' LIMIT ?') ? 'i' : 'ii'; }
  return [$sql, $types_all.$types_limit, array_merge($args_all, $args_limit), $count_sql, $types, $args];
}

function build_room_query(array $q, string $kw, string $sort, int $per_page, int $offset, bool $no_limit=false): array {
  $where = [];$args = [];$types = '';$order = '';
  $uid = (int)($GLOBALS['uid'] ?? 0);
  if ($kw !== '') {
    $like = '%' . $kw . '%';
    $where[] = '(rooms.title LIKE ? OR rooms.description LIKE ?)';
    $args[] = $like; $args[] = $like; $types .= 'ss';
  }
  if (!empty($q['room_type'])) { $where[] = 'rooms.room_type = ?'; $args[]=$q['room_type']; $types.='s'; }
  if ($q['price_min'] !== null) { $where[] = 'rooms.price_per_day >= ?'; $args[]=(float)$q['price_min']; $types.='d'; }
  if ($q['price_max'] !== null) { $where[] = 'rooms.price_per_day <= ?'; $args[]=(float)$q['price_max']; $types.='d'; }
  if ($q['beds'] !== null) { $where[] = 'rooms.beds >= ?'; $args[]=(int)$q['beds']; $types.='i'; }
  if ($q['maximum_guests'] !== null) { $where[] = 'rooms.maximum_guests >= ?'; $args[]=(int)$q['maximum_guests']; $types.='i'; }
  if (!empty($q['status'])) { $where[] = 'rooms.status = ?'; $args[]=$q['status']; $types.='s'; }
  if ($q['owner_id'] !== null) { $where[] = 'rooms.owner_id = ?'; $args[]=(int)$q['owner_id']; $types.='i'; }
  $where_sql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
  $limit_clause = $no_limit ? '' : ' LIMIT ? OFFSET ?';
  if ($sort === 'price_asc') { $order = 'ORDER BY rooms.price_per_day ASC, rooms.room_id DESC'; }
  elseif ($sort === 'price_desc') { $order = 'ORDER BY rooms.price_per_day DESC, rooms.room_id DESC'; }
  else { $order = 'ORDER BY rooms.created_at DESC, rooms.room_id DESC'; }
  $select_w = $uid>0 ? ', IF(rw.wishlist_id IS NULL, 0, 1) AS in_wishlist' : ', 0 AS in_wishlist';
  $join_w = $uid>0 ? ' LEFT JOIN room_wishlist rw ON rw.room_id = rooms.room_id AND rw.customer_id = ?' : '';
  $sql = 'SELECT rooms.room_id, rooms.room_code, rooms.title, rooms.description, rooms.price_per_day, rooms.beds, rooms.maximum_guests, rooms.status, rooms.room_type, rooms.created_at'
       . ', (SELECT ri.image_path FROM room_images ri WHERE ri.room_id = rooms.room_id ORDER BY ri.is_primary DESC, ri.image_id DESC LIMIT 1) AS image_path'
       . $select_w
       . ' FROM rooms'
       . $join_w
       . ' ' . $where_sql . ' ' . $order . $limit_clause;
  $count_sql = 'SELECT COUNT(1) AS c FROM rooms '.$where_sql;
  $args_join = $uid>0 ? [$uid] : [];
  $types_join = $uid>0 ? 'i' : '';
  $args_all = array_merge($args_join, $args);
  $types_all = $types_join . $types;
  if ($limit_clause === '') { $args_limit = []; $types_limit = ''; }
  else { $args_limit = ($limit_clause === ' LIMIT ?') ? [$per_page] : [$per_page, $offset]; $types_limit = ($limit_clause === ' LIMIT ?') ? 'i' : 'ii'; }
  return [$sql, $types_all.$types_limit, array_merge($args_all, $args_limit), $count_sql, $types, $args];
}

function get_param($key, $default=null) { return isset($_GET[$key]) && $_GET[$key] !== '' ? $_GET[$key] : $default; }
function get_param_int($key) { return isset($_GET[$key]) && $_GET[$key] !== '' ? (int)$_GET[$key] : null; }
function get_param_float($key) { return isset($_GET[$key]) && $_GET[$key] !== '' ? (float)$_GET[$key] : null; }
function get_param_scalar($key) { $v = $_GET[$key] ?? ''; return is_array($v) ? (string)reset($v) : $v; }

if ($type === 'property') {
  $cacheKey = 'adv_search_props_v2_' . http_build_query($_GET) . '_pp' . $per_page . '_pg' . $page . '_u' . $uid;
  if ($uid === 0) {
    $cached = app_cache_get($cacheKey, 60);
    if ($cached !== null) { [$rows,$cnt] = $cached; }
  }
  $q = [
    'property_type' => get_param('property_type',''),
    'price_min' => get_param_float('price_min'),
    'price_max' => get_param_float('price_max'),
    'bedrooms' => get_param_int('bedrooms'),
    'bathrooms' => get_param_int('bathrooms'),
    'living_rooms' => get_param_int('living_rooms'),
    'garden' => get_param('garden')!==null ? 1 : null,
    'gym' => get_param('gym')!==null ? 1 : null,
    'pool' => get_param('pool')!==null ? 1 : null,
    'kitchen' => get_param('kitchen')!==null ? 1 : null,
    'parking' => get_param('parking')!==null ? 1 : null,
    'status' => get_param('status',''),
    'sqft_min' => get_param_float('sqft_min'),
    'sqft_max' => get_param_float('sqft_max'),
    'owner_id' => get_param_int('owner_id'),
  ];
  if (!isset($rows) || !isset($cnt)) {
    [$sql,$types_all,$params_all,$count_sql,$types_count,$params_count] = build_property_query($q,$kw,$sort,$per_page,$offset,$per_page_all);
    $stmt = db()->prepare($sql);
    if ($types_all !== '') { $stmt->bind_param($types_all, ...$params_all); }
    $stmt->execute(); $res = $stmt->get_result(); $rows = []; while ($res && ($r=$res->fetch_assoc())) { $rows[]=$r; } $stmt->close();
    $cnt = 0; $cst = db()->prepare($count_sql); if ($types_count!=='') { $cst->bind_param($types_count, ...$params_count);} $cst->execute(); $cr=$cst->get_result()->fetch_assoc(); $cnt=(int)($cr['c']??0); $cst->close();
    if ($uid === 0) { app_cache_set($cacheKey, [$rows,$cnt]); }
  }
} else {
  $cacheKey = 'adv_search_rooms_v2_' . http_build_query($_GET) . '_pp' . $per_page . '_pg' . $page . '_u' . $uid;
  if ($uid === 0) {
    $cached = app_cache_get($cacheKey, 60);
    if ($cached !== null) { [$rows,$cnt] = $cached; }
  }
  $q = [
    'room_type' => get_param('room_type',''),
    'price_min' => get_param_float('price_min'),
    'price_max' => get_param_float('price_max'),
    'beds' => get_param_int('beds'),
    'maximum_guests' => get_param_int('maximum_guests'),
    'status' => get_param('status',''),
    'owner_id' => get_param_int('owner_id'),
  ];
  if (!isset($rows) || !isset($cnt)) {
    [$sql,$types_all,$params_all,$count_sql,$types_count,$params_count] = build_room_query($q,$kw,$sort,$per_page,$offset,$per_page_all);
    $stmt = db()->prepare($sql);
    if ($types_all !== '') { $stmt->bind_param($types_all, ...$params_all); }
    $stmt->execute(); $res = $stmt->get_result(); $rows = []; while ($res && ($r=$res->fetch_assoc())) { $rows[]=$r; } $stmt->close();
    $cnt = 0; $cst = db()->prepare($count_sql); if ($types_count!=='') { $cst->bind_param($types_count, ...$params_count);} $cst->execute(); $cr=$cst->get_result()->fetch_assoc(); $cnt=(int)($cr['c']??0); $cst->close();
    if ($uid === 0) { app_cache_set($cacheKey, [$rows,$cnt]); }
  }
}

$total_pages = $per_page_all ? 1 : max(1, (int)ceil($cnt / $per_page));

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function active($v,$c){return $v===$c?'active':'';}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Advanced Search</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Modern, readable typeface -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ==========================================================================
       RentalLanka UI Theme (scoped to .rl-theme)
       - Uses CSS variables for brand colors
       - Keeps Bootstrap structure; only adds classes and styles
       ========================================================================== */
    :root {
      /* Brand palette */
      --rl-primary: #004E98;   /* Primary */
      --rl-light: #EBEBEB;     /* Light background */
      --rl-secondary: #C0C0C0; /* Secondary */
      --rl-accent: #3A6EA5;    /* Accent */
      --rl-dark: #FF6700;      /* Accent/CTA (named "Dark" in brief) */

      /* Extended tokens */
      --rl-bg: #ffffff;
      --rl-text: #1f2a37;
      --rl-muted: #6b7280;
      --rl-border: #E5E7EB;
      --rl-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
      --rl-shadow-sm: 0 2px 12px rgba(0, 0, 0, 0.06);
      --rl-radius: 12px;
      --rl-focus: var(--rl-dark);
    }

    /* Global scope limiter */
    .rl-theme {
      font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--rl-text);
      background: var(--rl-bg);
    }

    /* Section spacing and page background */
    .rl-section { padding: clamp(1.25rem, 1.6vw + 0.5rem, 2.25rem) 0; }
    .rl-page-bg { background: linear-gradient(180deg, #fff 0%, var(--rl-light) 100%); }

    /* Navbar enhancements (works with your existing navbar include) */
    .rl-theme .navbar {
      background: #fff;
      border-bottom: 1px solid var(--rl-border);
    }
    .rl-theme .navbar .navbar-brand {
      font-weight: 700; color: var(--rl-primary);
    }
    .rl-theme .navbar .nav-link {
      color: var(--rl-text);
      font-weight: 500;
      border-radius: 8px;
      padding: 0.5rem 0.75rem;
      transition: color .2s ease, background-color .2s ease;
    }
    .rl-theme .navbar .nav-link:hover,
    .rl-theme .navbar .nav-link:focus { color: var(--rl-primary); background: rgba(0, 78, 152, 0.08); }

    /* Buttons (use with Bootstrap btn) */
    .rl-btn {
      border-radius: 999px;
      font-weight: 600;
      letter-spacing: 0.2px;
      transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, color .2s ease, border-color .2s ease;
    }
    .rl-btn:active { transform: translateY(1px); }
    .rl-btn-primary {
      background: var(--rl-primary); color: #fff; border-color: var(--rl-primary);
      box-shadow: var(--rl-shadow-sm);
    }
    .rl-btn-primary:hover { background: var(--rl-accent); border-color: var(--rl-accent); }
    .rl-btn-cta {
      background: var(--rl-dark); color: #111827; border-color: var(--rl-dark);
      box-shadow: var(--rl-shadow-sm);
    }
    .rl-btn-cta:hover { filter: brightness(0.95); }
    .rl-btn-outline { background: #fff; color: var(--rl-primary); border-color: var(--rl-primary); }
    .rl-btn-outline:hover { background: rgba(0, 78, 152, 0.06); }

    /* Cards and form elements */
    .rl-card {
      background: #fff;
      border: 1px solid var(--rl-border);
      border-radius: var(--rl-radius);
      box-shadow: var(--rl-shadow);
    }
    .rl-card .card-header {
      background: #fff;
      border-bottom: 1px solid var(--rl-border);
      border-top-left-radius: var(--rl-radius);
      border-top-right-radius: var(--rl-radius);
      font-weight: 700;
    }
    .rl-label { font-weight: 600; color: #111827; }
    .rl-control,
    .rl-select {
      border-radius: 10px;
      border-color: var(--rl-secondary);
      padding-block: 0.625rem;
    }
    .rl-control::placeholder { color: var(--rl-muted); }
    .rl-control:focus, .rl-select:focus {
      border-color: var(--rl-focus);
      box-shadow: 0 0 0 0.2rem rgba(255, 103, 0, 0.2);
    }
    .rl-range { accent-color: var(--rl-primary); }

    /* Filters panel (sticky on desktop, collapsible on mobile) */
    .rl-filters { position: sticky; top: 1rem; }
    @media (max-width: 991.98px) { .rl-filters { position: static; } }

    /* Listing cards */
    .rl-listing-card {
      border: 1px solid var(--rl-border);
      border-radius: var(--rl-radius);
      overflow: hidden;
      background: #fff;
      box-shadow: var(--rl-shadow-sm);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .rl-listing-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--rl-shadow);
      border-color: rgba(0, 78, 152, 0.25);
    }
    .rl-listing-media { background: var(--rl-light); }
    .rl-badge {
      position: absolute; top: .75rem; left: .75rem;
      background: rgba(255, 255, 255, 0.92);
      color: var(--rl-primary); border: 1px solid var(--rl-primary);
      border-radius: 999px; padding: .25rem .6rem; font-weight: 700; font-size: .8rem;
      box-shadow: var(--rl-shadow-sm); text-transform: uppercase;
    }
    .rl-listing-body { padding: 1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
    .rl-price { color: var(--rl-dark); font-weight: 800; letter-spacing: .2px; }
    .rl-meta { color: var(--rl-muted); }

    /* Pagination */
    .rl-page-link { border-radius: 8px !important; border-color: var(--rl-secondary); color: var(--rl-text); }
    .rl-page-item.active .rl-page-link { background-color: var(--rl-primary); border-color: var(--rl-primary); color: #fff; }

    /* Accessibility helpers */
    .rl-skip {
      position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;
    }
    .rl-skip:focus {
      position: static; width: auto; height: auto; background: #fff; padding: .5rem .75rem;
      border-radius: 8px; box-shadow: var(--rl-shadow); z-index: 1030;
    }

    /* Container width refinement on very large screens */
    @media (min-width: 1400px) { .container { max-width: 1200px; } }
  </style>
  </head>
<body class="rl-theme">
<a href="#main" class="rl-skip">Skip to content</a>
<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- Page content -->
<div id="main" class="rl-page-bg">
  <div class="container rl-section">

    <!-- Header row -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Advanced Search</h1>
      <div class="btn-group" role="group">
        <a class="btn rl-btn rl-btn-outline btn-outline-primary <?php echo active($type,'property'); ?>" href="?type=property&<?php echo h(http_build_query(array_diff_key($_GET,['type'=>1,'page'=>1]))); ?>">
          <i class="bi bi-building me-1"></i> Properties
        </a>
        <a class="btn rl-btn rl-btn-outline btn-outline-primary <?php echo active($type,'room'); ?>" href="?type=room&<?php echo h(http_build_query(array_diff_key($_GET,['type'=>1,'page'=>1]))); ?>">
          <i class="bi bi-door-open me-1"></i> Rooms
        </a>
      </div>
    </div>

    <!-- Mobile filters toggle -->
    <div class="d-lg-none mb-3">
      <button class="btn rl-btn rl-btn-outline w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse" aria-expanded="false" aria-controls="filtersCollapse">
        <i class="bi bi-sliders me-1"></i> Filters
      </button>
    </div>

    <div class="row g-4">
    <div class="col-lg-3">
      <div id="filtersCollapse" class="collapse d-lg-block">
        <div class="card rl-card rl-filters">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-sliders me-2"></i>Filters</span>
            <a href="?type=<?php echo h($type); ?>" class="small text-decoration-none">Clear</a>
          </div>
          <div class="card-body">
            <form method="get" class="vstack gap-3">
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <div>
              <label class="form-label rl-label">Keyword</label>
              <input type="text" class="form-control rl-control" name="q" value="<?php echo h($kw); ?>" placeholder="Title or description">
            </div>
            <div>
              <label class="form-label rl-label">Sort</label>
              <select name="sort" class="form-select rl-select">
                <option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option>
                <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                
              </select>
            </div>
            
            <?php if ($type==='property'): ?>
              <div>
                <label class="form-label rl-label">Property type</label>
                <select name="property_type" class="form-select rl-select">
                  <option value="">Any</option>
                  <?php foreach (['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'] as $pt): ?>
                    <option value="<?php echo h($pt); ?>" <?php echo ((string)get_param_scalar('property_type')===$pt)?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$pt)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Price min</label>
                  <input type="number" name="price_min" class="form-control rl-control" value="<?php echo h(get_param_scalar('price_min')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Price max</label>
                  <input type="number" name="price_max" class="form-control rl-control" value="<?php echo h(get_param_scalar('price_max')); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Bedrooms</label>
                  <input type="number" name="bedrooms" class="form-control rl-control" value="<?php echo h(get_param_scalar('bedrooms')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Bathrooms</label>
                  <input type="number" name="bathrooms" class="form-control rl-control" value="<?php echo h(get_param_scalar('bathrooms')); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Living rooms</label>
                  <input type="number" name="living_rooms" class="form-control rl-control" value="<?php echo h(get_param_scalar('living_rooms')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Status</label>
                  <select name="status" class="form-select rl-select">
                    <option value="">Any</option>
                    <?php foreach (['pending','available','unavailable'] as $s): ?>
                      <option value="<?php echo h($s); ?>" <?php echo ((string)get_param_scalar('status')===$s)?'selected':''; ?>><?php echo ucwords($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Sqft min</label>
                  <input type="number" step="0.01" name="sqft_min" class="form-control rl-control" value="<?php echo h(get_param_scalar('sqft_min')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Sqft max</label>
                  <input type="number" step="0.01" name="sqft_max" class="form-control rl-control" value="<?php echo h(get_param_scalar('sqft_max')); ?>">
                </div>
              </div>
              <div class="row g-2">
                <?php foreach (['garden'=>'Garden','gym'=>'Gym','pool'=>'Pool','kitchen'=>'Kitchen','parking'=>'Parking'] as $k=>$label): ?>
                  <div class="col-6">
                    <div class="form-check">
                      <input class="form-check-input rl-focusable" type="checkbox" name="<?php echo h($k); ?>" id="f_<?php echo h($k); ?>" <?php echo isset($_GET[$k])?'checked':''; ?>>
                      <label class="form-check-label" for="f_<?php echo h($k); ?>"><?php echo h($label); ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div>
                <label class="form-label rl-label">Room type</label>
                <select name="room_type" class="form-select rl-select">
                  <option value="">Any</option>
                  <?php foreach (['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'] as $rt): ?>
                    <option value="<?php echo h($rt); ?>" <?php echo ((string)get_param_scalar('room_type')===$rt)?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$rt)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Price min</label>
                  <input type="number" name="price_min" class="form-control rl-control" value="<?php echo h(get_param_scalar('price_min')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Price max</label>
                  <input type="number" name="price_max" class="form-control rl-control" value="<?php echo h(get_param_scalar('price_max')); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label rl-label">Beds</label>
                  <input type="number" name="beds" class="form-control rl-control" value="<?php echo h(get_param_scalar('beds')); ?>">
                </div>
                <div class="col">
                  <label class="form-label rl-label">Max guests</label>
                  <input type="number" name="maximum_guests" class="form-control rl-control" value="<?php echo h(get_param_scalar('maximum_guests')); ?>">
                </div>
              </div>
              <div>
                <label class="form-label rl-label">Status</label>
                <select name="status" class="form-select rl-select">
                  <option value="">Any</option>
                  <?php foreach (['pending','available','unavailable','rented'] as $s): ?>
                    <option value="<?php echo h($s); ?>" <?php echo ((string)get_param_scalar('status')===$s)?'selected':''; ?>><?php echo ucwords($s); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
              <button class="btn rl-btn rl-btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
              <a class="btn rl-btn rl-btn-outline btn-outline-secondary" href="?type=<?php echo h($type); ?>">Reset</a>
            </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    <div class="col-lg-9">
      <div class="mb-3">
        
        <div class="d-flex align-items-center justify-content-between">
          <div><strong><?php echo (int)$cnt; ?></strong> results</div>
          <div>
            <form method="get" class="d-flex align-items-center gap-2">
              <?php
                foreach ($_GET as $k=>$v) {
                  if ($k==='per_page') continue;
                  if (is_array($v)) {
                    foreach ($v as $sv) {
                      echo '<input type="hidden" name="'.h($k).'[]" value="'.h($sv).'">';
                    }
                  } else {
                    echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
                  }
                }
              ?>
              <label class="form-label m-0 text-nowrap me-2">Per&nbsp;page</label>
              <select name="per_page" class="form-select form-select-sm w-auto rl-select" onchange="this.form.submit()">
                <?php foreach ([6,12,18,24] as $pp): ?>
                  <option value="<?php echo $pp; ?>" <?php echo (!$per_page_all && $per_page===$pp)?'selected':''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
                <option value="all" <?php echo $per_page_all?'selected':''; ?>>All</option>
              </select>
            </form>
          </div>
        </div>
      </div>

      <?php if ($type==='property'): ?>
        <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-xl-3">
          <?php foreach ($rows as $it): ?>
            <div class="col">
              <div class="card rl-listing-card h-100 border shadow-sm position-relative overflow-hidden">
                <?php if (!empty($it['status'])): ?>
                  <span class="rl-badge"><?php echo h($it['status']); ?></span>
                <?php endif; ?>
                <?php
                  $img = (string)($it['image'] ?? '');
                  if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); }
                  if ($img === '') { $img = 'https://via.placeholder.com/800x450?text=No+Image'; }
                ?>
                <div class="ratio ratio-16x9 rl-listing-media">
                  <img src="<?php echo h($img); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo h($it['title']); ?>" loading="lazy" decoding="async">
                </div>
                <div class="card-body rl-listing-body">
                  <h5 class="card-title mb-1"><?php echo h($it['title']); ?></h5>
                  <div class="rl-meta small mb-2"><?php echo h(ucwords(str_replace('_',' ',$it['property_type']))); ?></div>
                  <div class="mt-auto">
                    <span class="rl-price">LKR <?php echo number_format((float)$it['price_per_month'], 2); ?>/month</span>
                  </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                  <div class="row g-2">
                    <div class="col-6">
                      <a class="btn rl-btn rl-btn-outline btn-sm btn-outline-secondary w-100" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$it['property_id']; ?>">
                        <i class="bi bi-eye me-1"></i>View
                      </a>
                    </div>
                    <div class="col-6">
                      <?php $in = (int)($it['in_wishlist'] ?? 0) === 1; ?>
                      <button class="btn rl-btn btn-sm w-100 btn-wish <?php echo $in ? 'btn-outline-danger' : 'btn-outline-primary'; ?>" data-id="<?php echo (int)$it['property_id']; ?>">
                        <?php if ($in): ?>
                          <i class="bi bi-heart-fill"></i> Added
                        <?php else: ?>
                          <i class="bi bi-heart"></i> Wishlist
                        <?php endif; ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <div class="col-12"><div class="alert alert-info">No results.</div></div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-xl-3">
          <?php foreach ($rows as $it): ?>
            <div class="col">
              <div class="card rl-listing-card h-100 border shadow-sm position-relative overflow-hidden">
                <?php if (!empty($it['status'])): ?>
                  <span class="rl-badge"><?php echo h($it['status']); ?></span>
                <?php endif; ?>
                <?php
                  $img = (string)($it['image_path'] ?? '');
                  if ($img !== '' && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); }
                  if ($img === '') { $img = 'https://via.placeholder.com/800x450?text=No+Image'; }
                ?>
                <div class="ratio ratio-16x9 rl-listing-media">
                  <img src="<?php echo h($img); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo h($it['title']); ?>" loading="lazy" decoding="async">
                </div>
                <div class="card-body rl-listing-body">
                  <h5 class="card-title mb-1"><?php echo h($it['title']); ?></h5>
                  <?php $rt = (string)($it['room_type'] ?? ''); ?>
                  <div class="rl-meta small mb-2"><?php echo h(ucwords(str_replace('_',' ',$rt))); ?></div>
                  <div class="rl-price">LKR <?php echo number_format((float)($it['price_per_day'] ?? 0), 2); ?>/day</div>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-bed me-1"></i><?php echo (int)($it['beds'] ?? 0); ?>
                    <span class="ms-2"><i class="bi bi-people me-1"></i><?php echo (int)($it['maximum_guests'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                  <?php $rid = (int)($it['room_id'] ?? 0); ?>
                  <div class="row g-2">
                    <div class="col-6">
                      <a class="btn rl-btn rl-btn-outline btn-sm btn-outline-secondary w-100 <?php echo $rid? '' : 'disabled'; ?>" href="<?php echo $rid ? ($base_url . '/public/includes/view_room.php?id=' . $rid) : '#'; ?>">
                        <i class="bi bi-eye me-1"></i>View
                      </a>
                    </div>
                    <div class="col-6">
                      <?php $rin = (int)($it['in_wishlist'] ?? 0) === 1; ?>
                      <button class="btn rl-btn btn-sm w-100 btn-room-wish <?php echo $rin ? 'btn-outline-danger' : 'btn-outline-primary'; ?>" data-id="<?php echo $rid; ?>" <?php echo $rid? '' : 'disabled'; ?>>
                        <?php if ($rin): ?>
                          <i class="bi bi-heart-fill"></i> Added
                        <?php else: ?>
                          <i class="bi bi-heart"></i> Wishlist
                        <?php endif; ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <div class="col-12"><div class="alert alert-info">No results.</div></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination">
          <?php
            $qs = $_GET; unset($qs['page']);
            $base = '?' . http_build_query($qs);
            $prev = max(1, $page-1); $next = min($total_pages, $page+1);
          ?>
          <li class="page-item rl-page-item <?php echo $page<=1?'disabled':''; ?>">
            <a class="page-link rl-page-link" href="<?php echo h($base . '&page=' . $prev); ?>">Previous</a>
          </li>
          <?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
            <li class="page-item rl-page-item <?php echo $p===$page?'active':''; ?>">
              <a class="page-link rl-page-link" href="<?php echo h($base . '&page=' . $p); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item rl-page-item <?php echo $page>=$total_pages?'disabled':''; ?>">
            <a class="page-link rl-page-link" href="<?php echo h($base . '&page=' . $next); ?>">Next</a>
          </li>
        </ul>
      </nav>
      
    </div>
  </div>
</div>
</div>
<script>
  async function wishToggle(btn, id) {
    if (!id) return;
    btn.disabled = true;
    try {
      const statusRes = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php?action=status&property_id=' + id);
      const s = await statusRes.json();
      const act = (s && s.in_wishlist) ? 'remove' : 'add';
      const res = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: act, property_id: String(id) })
      });
      const data = await res.json();
      if (data.status === 'success' || data.status === 'exists') {
        if (act === 'add') {
          btn.classList.remove('btn-outline-primary');
          btn.classList.add('btn-outline-danger');
          btn.innerHTML = '<i class="bi bi-heart-fill"></i> Added';
        } else {
          btn.classList.remove('btn-outline-danger');
          btn.classList.add('btn-outline-primary');
          btn.innerHTML = '<i class="bi bi-heart"></i> Wishlist';
        }
      } else if (data.status === 'error') {
        const msg = String(data.message || 'Action failed');
        if (window.Swal) {
          if (/Please log in first/i.test(msg)) {
            Swal.fire({ icon: 'warning', title: 'Login required', text: msg, showCancelButton: true, confirmButtonText: 'Login', cancelButtonText: 'Close' })
              .then(r => { if (r.isConfirmed) { window.location.href = '<?php echo $base_url; ?>/auth/login.php'; } });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonText: 'OK' });
          }
        } else {
          alert(msg);
        }
      }
    } catch (e) {
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.', confirmButtonText: 'OK' });
      } else {
        alert('Network error');
      }
    } finally {
      btn.disabled = false;
    }
  }
  async function roomWishToggle(btn, id) {
    if (!id) return;
    btn.disabled = true;
    try {
      const statusRes = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php?action=status&type=room&room_id=' + id);
      const s = await statusRes.json();
      const act = (s && s.in_wishlist) ? 'remove' : 'add';
      const res = await fetch('<?php echo $base_url; ?>/public/includes/wishlist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: act, type: 'room', room_id: String(id) })
      });
      const data = await res.json();
      if (data.status === 'success' || data.status === 'exists') {
        if (act === 'add') {
          btn.classList.remove('btn-outline-primary');
          btn.classList.add('btn-outline-danger');
          btn.innerHTML = '<i class="bi bi-heart-fill"></i> Added';
        } else {
          btn.classList.remove('btn-outline-danger');
          btn.classList.add('btn-outline-primary');
          btn.innerHTML = '<i class="bi bi-heart"></i> Wishlist';
        }
      } else if (data.status === 'error') {
        const msg = String(data.message || 'Action failed');
        if (window.Swal) {
          if (/Please log in first/i.test(msg)) {
            Swal.fire({ icon: 'warning', title: 'Login required', text: msg, showCancelButton: true, confirmButtonText: 'Login', cancelButtonText: 'Close' })
              .then(r => { if (r.isConfirmed) { window.location.href = '<?php echo $base_url; ?>/auth/login.php'; } });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonText: 'OK' });
          }
        } else {
          alert(msg);
        }
      }
    } catch (e) {
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.', confirmButtonText: 'OK' });
      } else {
        alert('Network error');
      }
    } finally {
      btn.disabled = false;
    }
  }
  document.addEventListener('click', (e) => {
    const bw = e.target.closest('.btn-wish');
    if (bw) { const id = parseInt(bw.getAttribute('data-id')||'0',10); if (id) wishToggle(bw, id); return; }
    const br = e.target.closest('.btn-room-wish');
    if (br) { const id = parseInt(br.getAttribute('data-id')||'0',10); if (id) roomWishToggle(br, id); }
  });
</script>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

