<?php
require_once __DIR__ . '/../../config/config.php';

$type = ($_GET['type'] ?? 'property');
if ($type !== 'room') { $type = 'property'; }
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(1, min(30, (int)($_GET['per_page'] ?? 12)));
$offset = ($page - 1) * $per_page;

$owners = [];
try {
  $rs = db()->query("SELECT user_id, COALESCE(NULLIF(CONCAT(TRIM(first_name),' ',TRIM(last_name)),' '), name, CONCAT('Owner #', user_id)) AS name FROM users WHERE role='owner' ORDER BY user_id DESC LIMIT 200");
  while ($rs && ($r = $rs->fetch_assoc())) { $owners[] = $r; }
} catch (Throwable $e) {}

$kw = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'newest');

function build_property_query(array $q, string $kw, string $sort, int $per_page, int $offset): array {
  $where = [];$args = [];$types = '';$select_extra = '';$order = '';
  if ($kw !== '') { $where[] = '(title LIKE ? OR description LIKE ?)'; $like = '%' . $kw . '%'; $args[]=$like;$args[]=$like;$types.='ss'; $select_extra = ', (CASE WHEN title LIKE ? THEN 2 ELSE 0 END + CASE WHEN description LIKE ? THEN 1 ELSE 0 END) AS rel'; $args_rel = ['%'.$kw.'%','%'.$kw.'%']; }
  if (!empty($q['property_type'])) { $where[] = 'property_type = ?'; $args[]=$q['property_type']; $types.='s'; }
  if ($q['price_min'] !== null) { $where[] = 'price_per_month >= ?'; $args[]=(float)$q['price_min']; $types.='d'; }
  if ($q['price_max'] !== null) { $where[] = 'price_per_month <= ?'; $args[]=(float)$q['price_max']; $types.='d'; }
  if ($q['bedrooms'] !== null) { $where[] = 'bedrooms >= ?'; $args[]=(int)$q['bedrooms']; $types.='i'; }
  if ($q['bathrooms'] !== null) { $where[] = 'bathrooms >= ?'; $args[]=(int)$q['bathrooms']; $types.='i'; }
  if ($q['living_rooms'] !== null) { $where[] = 'living_rooms >= ?'; $args[]=(int)$q['living_rooms']; $types.='i'; }
  foreach (['garden','gym','pool','kitchen','parking'] as $b) { if ($q[$b] !== null) { $where[] = $b.' = ?'; $args[]=(int)$q[$b]; $types.='i'; } }
  if (!empty($q['status'])) { $where[] = 'status = ?'; $args[]=$q['status']; $types.='s'; }
  if ($q['sqft_min'] !== null) { $where[] = 'sqft >= ?'; $args[]=(float)$q['sqft_min']; $types.='d'; }
  if ($q['sqft_max'] !== null) { $where[] = 'sqft <= ?'; $args[]=(float)$q['sqft_max']; $types.='d'; }
  if ($q['owner_id'] !== null) { $where[] = 'owner_id = ?'; $args[]=(int)$q['owner_id']; $types.='i'; }
  $where_sql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
  if ($sort === 'price_asc') { $order = 'ORDER BY price_per_month ASC, property_id DESC'; }
  elseif ($sort === 'price_desc') { $order = 'ORDER BY price_per_month DESC, property_id DESC'; }
  elseif ($sort === 'relevance' && $kw !== '') { $order = 'ORDER BY rel DESC, property_id DESC'; }
  else { $order = 'ORDER BY created_at DESC, property_id DESC'; }
  $sql = 'SELECT property_id, property_code, title, description, image, price_per_month, bedrooms, bathrooms, living_rooms, garden, gym, pool, kitchen, parking, property_type, status, created_at'.$select_extra.' FROM properties '.$where_sql.' '.$order.' LIMIT ? OFFSET ?';
  $count_sql = 'SELECT COUNT(1) AS c FROM properties '.$where_sql;
  $args_all = $args; $types_all = $types;
  if ($kw !== '') { $args_all = array_merge($args, $args_rel); $types_all .= 'ss'; }
  $args_limit = [$per_page, $offset]; $types_limit = 'ii';
  return [$sql, $types_all.$types_limit, array_merge($args_all, $args_limit), $count_sql, $types, $args];
}

function build_room_query(array $q, string $kw, string $sort, int $per_page, int $offset): array {
  $where = [];$args = [];$types = '';$select_extra = '';$order = '';
  if ($kw !== '') { $where[] = '(title LIKE ? OR description LIKE ?)'; $like = '%' . $kw . '%'; $args[]=$like;$args[]=$like;$types.='ss'; $select_extra = ', (CASE WHEN title LIKE ? THEN 2 ELSE 0 END + CASE WHEN description LIKE ? THEN 1 ELSE 0 END) AS rel'; $args_rel = ['%'.$kw.'%','%'.$kw.'%']; }
  if (!empty($q['room_type'])) { $where[] = 'room_type = ?'; $args[]=$q['room_type']; $types.='s'; }
  if ($q['price_min'] !== null) { $where[] = 'price_per_day >= ?'; $args[]=(float)$q['price_min']; $types.='d'; }
  if ($q['price_max'] !== null) { $where[] = 'price_per_day <= ?'; $args[]=(float)$q['price_max']; $types.='d'; }
  if ($q['beds'] !== null) { $where[] = 'beds >= ?'; $args[]=(int)$q['beds']; $types.='i'; }
  if ($q['maximum_guests'] !== null) { $where[] = 'maximum_guests >= ?'; $args[]=(int)$q['maximum_guests']; $types.='i'; }
  if (!empty($q['status'])) { $where[] = 'status = ?'; $args[]=$q['status']; $types.='s'; }
  if ($q['owner_id'] !== null) { $where[] = 'owner_id = ?'; $args[]=(int)$q['owner_id']; $types.='i'; }
  $where_sql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
  if ($sort === 'price_asc') { $order = 'ORDER BY price_per_day ASC, room_id DESC'; }
  elseif ($sort === 'price_desc') { $order = 'ORDER BY price_per_day DESC, room_id DESC'; }
  elseif ($sort === 'relevance' && $kw !== '') { $order = 'ORDER BY rel DESC, room_id DESC'; }
  else { $order = 'ORDER BY created_at DESC, room_id DESC'; }
  $sql = 'SELECT room_id, room_code, title, description, price_per_day, beds, maximum_guests, status, room_type, created_at'.$select_extra.' FROM rooms '.$where_sql.' '.$order.' LIMIT ? OFFSET ?';
  $count_sql = 'SELECT COUNT(1) AS c FROM rooms '.$where_sql;
  $args_all = $args; $types_all = $types;
  if ($kw !== '') { $args_all = array_merge($args, $args_rel); $types_all .= 'ss'; }
  $args_limit = [$per_page, $offset]; $types_limit = 'ii';
  return [$sql, $types_all.$types_limit, array_merge($args_all, $args_limit), $count_sql, $types, $args];
}

function get_param($key, $default=null) { return isset($_GET[$key]) && $_GET[$key] !== '' ? $_GET[$key] : $default; }
function get_param_int($key) { return isset($_GET[$key]) && $_GET[$key] !== '' ? (int)$_GET[$key] : null; }
function get_param_float($key) { return isset($_GET[$key]) && $_GET[$key] !== '' ? (float)$_GET[$key] : null; }

if ($type === 'property') {
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
  [$sql,$types_all,$params_all,$count_sql,$types_count,$params_count] = build_property_query($q,$kw,$sort,$per_page,$offset);
  $stmt = db()->prepare($sql);
  if ($types_all !== '') { $stmt->bind_param($types_all, ...$params_all); }
  $stmt->execute(); $res = $stmt->get_result(); $rows = []; while ($res && ($r=$res->fetch_assoc())) { $rows[]=$r; } $stmt->close();
  $cnt = 0; $cst = db()->prepare($count_sql); if ($types_count!=='') { $cst->bind_param($types_count, ...$params_count);} $cst->execute(); $cr=$cst->get_result()->fetch_assoc(); $cnt=(int)($cr['c']??0); $cst->close();
} else {
  $q = [
    'room_type' => get_param('room_type',''),
    'price_min' => get_param_float('price_min'),
    'price_max' => get_param_float('price_max'),
    'beds' => get_param_int('beds'),
    'maximum_guests' => get_param_int('maximum_guests'),
    'status' => get_param('status',''),
    'owner_id' => get_param_int('owner_id'),
  ];
  [$sql,$types_all,$params_all,$count_sql,$types_count,$params_count] = build_room_query($q,$kw,$sort,$per_page,$offset);
  $stmt = db()->prepare($sql);
  if ($types_all !== '') { $stmt->bind_param($types_all, ...$params_all); }
  $stmt->execute(); $res = $stmt->get_result(); $rows = []; while ($res && ($r=$res->fetch_assoc())) { $rows[]=$r; } $stmt->close();
  $cnt = 0; $cst = db()->prepare($count_sql); if ($types_count!=='') { $cst->bind_param($types_count, ...$params_count);} $cst->execute(); $cr=$cst->get_result()->fetch_assoc(); $cnt=(int)($cr['c']??0); $cst->close();
}

$total_pages = max(1, (int)ceil($cnt / $per_page));

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
  <style>
    .filter-card{position:sticky;top:1rem}
    .badge-filter{margin-right:.25rem}
  </style>
  </head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Advanced Search</h1>
    <div class="btn-group" role="group">
      <a class="btn btn-outline-primary <?php echo active($type,'property'); ?>" href="?type=property&<?php echo h(http_build_query(array_diff_key($_GET,['type'=>1,'page'=>1]))); ?>">Properties</a>
      <a class="btn btn-outline-primary <?php echo active($type,'room'); ?>" href="?type=room&<?php echo h(http_build_query(array_diff_key($_GET,['type'=>1,'page'=>1]))); ?>">Rooms</a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-3">
      <div class="card filter-card">
        <div class="card-header">Filters</div>
        <div class="card-body">
          <form method="get" class="vstack gap-3">
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <div>
              <label class="form-label">Keyword</label>
              <input type="text" class="form-control" name="q" value="<?php echo h($kw); ?>" placeholder="Title or description">
            </div>
            <div>
              <label class="form-label">Sort</label>
              <select name="sort" class="form-select">
                <option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option>
                <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                <option value="relevance" <?php echo $sort==='relevance'?'selected':''; ?>>Relevance</option>
              </select>
            </div>
            <div>
              <label class="form-label">Owner</label>
              <select name="owner_id" class="form-select">
                <option value="">Any</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo (int)$o['user_id']; ?>" <?php echo ((int)($_GET['owner_id']??0) === (int)$o['user_id'])?'selected':''; ?>><?php echo (int)$o['user_id']; ?> - <?php echo h($o['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($type==='property'): ?>
              <div>
                <label class="form-label">Property type</label>
                <select name="property_type" class="form-select">
                  <option value="">Any</option>
                  <?php foreach (['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'] as $pt): ?>
                    <option value="<?php echo h($pt); ?>" <?php echo (($
                      $_GET['property_type']??'')===$pt)?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$pt)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Price min</label>
                  <input type="number" name="price_min" class="form-control" value="<?php echo h($_GET['price_min']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Price max</label>
                  <input type="number" name="price_max" class="form-control" value="<?php echo h($_GET['price_max']??''); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Bedrooms</label>
                  <input type="number" name="bedrooms" class="form-control" value="<?php echo h($_GET['bedrooms']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Bathrooms</label>
                  <input type="number" name="bathrooms" class="form-control" value="<?php echo h($_GET['bathrooms']??''); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Living rooms</label>
                  <input type="number" name="living_rooms" class="form-control" value="<?php echo h($_GET['living_rooms']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <option value="">Any</option>
                    <?php foreach (['pending','available','unavailable'] as $s): ?>
                      <option value="<?php echo h($s); ?>" <?php echo (($
                        $_GET['status']??'')===$s)?'selected':''; ?>><?php echo ucwords($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Sqft min</label>
                  <input type="number" step="0.01" name="sqft_min" class="form-control" value="<?php echo h($_GET['sqft_min']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Sqft max</label>
                  <input type="number" step="0.01" name="sqft_max" class="form-control" value="<?php echo h($_GET['sqft_max']??''); ?>">
                </div>
              </div>
              <div class="row g-2">
                <?php foreach (['garden'=>'Garden','gym'=>'Gym','pool'=>'Pool','kitchen'=>'Kitchen','parking'=>'Parking'] as $k=>$label): ?>
                  <div class="col-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="<?php echo h($k); ?>" id="f_<?php echo h($k); ?>" <?php echo isset($_GET[$k])?'checked':''; ?>>
                      <label class="form-check-label" for="f_<?php echo h($k); ?>"><?php echo h($label); ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div>
                <label class="form-label">Room type</label>
                <select name="room_type" class="form-select">
                  <option value="">Any</option>
                  <?php foreach (['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'] as $rt): ?>
                    <option value="<?php echo h($rt); ?>" <?php echo (($
                      $_GET['room_type']??'')===$rt)?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$rt)); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Price min</label>
                  <input type="number" name="price_min" class="form-control" value="<?php echo h($_GET['price_min']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Price max</label>
                  <input type="number" name="price_max" class="form-control" value="<?php echo h($_GET['price_max']??''); ?>">
                </div>
              </div>
              <div class="row g-2">
                <div class="col">
                  <label class="form-label">Beds</label>
                  <input type="number" name="beds" class="form-control" value="<?php echo h($_GET['beds']??''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Max guests</label>
                  <input type="number" name="maximum_guests" class="form-control" value="<?php echo h($_GET['maximum_guests']??''); ?>">
                </div>
              </div>
              <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="">Any</option>
                  <?php foreach (['pending','available','unavailable','rented'] as $s): ?>
                    <option value="<?php echo h($s); ?>" <?php echo (($
                      $_GET['status']??'')===$s)?'selected':''; ?>><?php echo ucwords($s); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
              <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
              <a class="btn btn-outline-secondary" href="?type=<?php echo h($type); ?>">Reset</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-9">
      <div class="mb-3">
        <?php
          $badges = [];
          foreach ($_GET as $k=>$v) {
            if ($k==='type' || $k==='page' || $v==='') continue;
            $badges[] = '<span class="badge bg-secondary badge-filter">'.h($k).': '.h(is_array($v)?implode(',',$v):$v).'</span>';
          }
          echo $badges ? '<div class="mb-2">'.implode(' ',$badges).'</div>' : '';
        ?>
        <div class="d-flex align-items-center justify-content-between">
          <div><strong><?php echo (int)$cnt; ?></strong> results</div>
          <div>
            <form method="get" class="d-flex align-items-center gap-2">
              <?php foreach ($_GET as $k=>$v) { if ($k==='per_page') continue; echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">'; }
              ?>
              <label class="form-label m-0">Per page</label>
              <select name="per_page" class="form-select" onchange="this.form.submit()">
                <?php foreach ([6,12,18,24] as $pp): ?>
                  <option value="<?php echo $pp; ?>" <?php echo ($per_page===$pp)?'selected':''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
      </div>

      <?php if ($type==='property'): ?>
        <div class="row g-3">
          <?php foreach ($rows as $it): ?>
            <div class="col-md-6 col-xl-4">
              <div class="card h-100">
                <?php if (!empty($it['image'])): ?>
                  <img src="<?php echo h($it['image']); ?>" class="card-img-top" alt="">
                <?php endif; ?>
                <div class="card-body">
                  <h6 class="card-title mb-1"><?php echo h($it['title']); ?></h6>
                  <div class="text-muted small mb-2"><?php echo h(ucwords(str_replace('_',' ',$it['property_type']))); ?> • <?php echo h($it['status']); ?></div>
                  <div class="fw-semibold">LKR <?php echo number_format((float)$it['price_per_month'], 2); ?>/mo</div>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-door-closed me-1"></i><?php echo (int)$it['bedrooms']; ?>
                    <span class="ms-2"><i class="bi bi-droplet me-1"></i><?php echo (int)$it['bathrooms']; ?></span>
                    <span class="ms-2"><i class="bi bi-house me-1"></i><?php echo (int)$it['living_rooms']; ?></span>
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
        <div class="row g-3">
          <?php foreach ($rows as $it): ?>
            <div class="col-md-6 col-xl-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="card-title mb-1"><?php echo h($it['title']); ?></h6>
                  <div class="text-muted small mb-2"><?php echo h(ucwords(str_replace('_',' ',$it['room_type']))); ?> • <?php echo h($it['status']); ?></div>
                  <div class="fw-semibold">LKR <?php echo number_format((float)$it['price_per_day'], 2); ?>/day</div>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-bed me-1"></i><?php echo (int)$it['beds']; ?>
                    <span class="ms-2"><i class="bi bi-people me-1"></i><?php echo (int)$it['maximum_guests']; ?></span>
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
          <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
            <a class="page-link" href="<?php echo h($base . '&page=' . $prev); ?>">Previous</a>
          </li>
          <?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
            <li class="page-item <?php echo $p===$page?'active':''; ?>">
              <a class="page-link" href="<?php echo h($base . '&page=' . $p); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>">
            <a class="page-link" href="<?php echo h($base . '&page=' . $next); ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

