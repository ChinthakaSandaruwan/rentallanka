<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';

// Handle adds/deletes for provinces, districts, cities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_province') {
      $name = trim($_POST['province_name'] ?? '');
      if ($name === '') { $err = 'Province name required'; }
      else {
        $stmt = db()->prepare('INSERT INTO provinces (name) VALUES (?)');
        $stmt->bind_param('s', $name);
        if ($stmt->execute()) { $ok = 'Province added'; } else { $err = 'Insert failed'; }
        $stmt->close();
      }
    } elseif ($action === 'add_district') {
      $province_id = (int)($_POST['province_id'] ?? 0);
      $name = trim($_POST['district_name'] ?? '');
      if ($province_id <= 0 || $name === '') { $err = 'Province and district required'; }
      else {
        $stmt = db()->prepare('INSERT INTO districts (province_id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $province_id, $name);
        if ($stmt->execute()) { $ok = 'District added'; } else { $err = 'Insert failed'; }
        $stmt->close();
      }
    } elseif ($action === 'add_city') {
      $district_id = (int)($_POST['district_id'] ?? 0);
      $name = trim($_POST['city_name'] ?? '');
      if ($district_id <= 0 || $name === '') { $err = 'District and city required'; }
      else {
        $stmt = db()->prepare('INSERT INTO cities (district_id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $district_id, $name);
        if ($stmt->execute()) { $ok = 'City added'; } else { $err = 'Insert failed'; }
        $stmt->close();
      }
    } elseif ($action === 'wipe_locations') {
      $db = db();
      $db->begin_transaction();
      $ok = '';
      try {
        // Delete in FK-safe order
        if (!$db->query('DELETE FROM locations')) { throw new Exception('Delete all failed (locations)'); }
        if (!$db->query('DELETE FROM cities')) { throw new Exception('Delete all failed (cities)'); }
        if (!$db->query('DELETE FROM districts')) { throw new Exception('Delete all failed (districts)'); }
        if (!$db->query('DELETE FROM provinces')) { throw new Exception('Delete all failed (provinces)'); }
        $db->commit();
        $ok = 'All location data deleted';
      } catch (Throwable $e) {
        $db->rollback();
        $err = $e->getMessage();
      }
    } elseif ($action === 'delete' && isset($_POST['type'])) {
      $type = $_POST['type'];
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) { $err = 'Bad input'; }
      else {
        if ($type === 'province') {
          $d = db()->prepare('DELETE FROM provinces WHERE province_id=?');
          $d->bind_param('i', $id);
        } elseif ($type === 'district') {
          $d = db()->prepare('DELETE FROM districts WHERE district_id=?');
          $d->bind_param('i', $id);
        } else {
          $d = db()->prepare('DELETE FROM cities WHERE city_id=?');
          $d->bind_param('i', $id);
        }
        if ($d->execute() && $d->affected_rows > 0) { $ok = 'Deleted'; } else { $err = 'Delete failed (check dependencies)'; }
        $d->close();
      }
    }
  }
}

// AJAX for cascading selects
if (isset($_GET['geo'])) {
  header('Content-Type: application/json');
  $t = $_GET['geo'];
  if ($t === 'provinces') {
    $out = [];
    $res = db()->query('SELECT province_id, name FROM provinces ORDER BY name');
    while ($r = $res->fetch_assoc()) { $out[] = $r; }
    echo json_encode($out); exit;
  } elseif ($t === 'districts') {
    $pid = (int)($_GET['province_id'] ?? 0);
    $out = [];
    $st = db()->prepare('SELECT district_id, name FROM districts WHERE province_id=? ORDER BY name');
    $st->bind_param('i', $pid);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) { $out[] = $r; }
    $st->close();
    echo json_encode($out); exit;
  } elseif ($t === 'cities') {
    $did = (int)($_GET['district_id'] ?? 0);
    $out = [];
    $st = db()->prepare('SELECT city_id, name FROM cities WHERE district_id=? ORDER BY name');
    $st->bind_param('i', $did);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) { $out[] = $r; }
    $st->close();
    echo json_encode($out); exit;
  }
  echo json_encode([]); exit;
}

// Load current lists
$provinces = [];
$res1 = db()->query('SELECT province_id, name, created_at FROM provinces ORDER BY name');
while ($r = $res1->fetch_assoc()) { $provinces[] = $r; }

$districts = [];
$res2 = db()->query('SELECT d.district_id, d.name, d.created_at, p.name AS province_name, d.province_id FROM districts d JOIN provinces p ON p.province_id=d.province_id ORDER BY p.name, d.name');
while ($r = $res2->fetch_assoc()) { $districts[] = $r; }

$cities = [];
$res3 = db()->query('SELECT c.city_id, c.name, c.created_at, d.name AS district_name, p.name AS province_name, c.district_id FROM cities c JOIN districts d ON d.district_id=c.district_id JOIN provinces p ON p.province_id=d.province_id ORDER BY p.name, d.name, c.name');
while ($r = $res3->fetch_assoc()) { $cities[] = $r; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - Location Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Location Management</h1>
    <div class="d-flex gap-2">
      <form method="post" class="d-inline" onsubmit="return confirm('Delete ALL provinces, districts, and cities? This cannot be undone and may fail if referenced by other data.');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="wipe_locations">
        <button class="btn btn-danger btn-sm">Delete All Location Data</button>
      </form>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
  </div>
  <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">Add Province</div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_province">
            <div class="col-12">
              <label class="form-label">Province Name</label>
              <input name="province_name" class="form-control" required>
            </div>
            <div class="col-12"><button class="btn btn-primary">Add</button></div>
          </form>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Provinces</div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($provinces as $p): ?>
              <tr>
                <td><?php echo (int)$p['province_id']; ?></td>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete province? Dependent districts and cities will be deleted.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="type" value="province">
                    <input type="hidden" name="id" value="<?php echo (int)$p['province_id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$provinces): ?><tr><td colspan="4" class="text-center py-3">No provinces</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">Add District</div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_district">
            <div class="col-12">
              <label class="form-label">Province</label>
              <select name="province_id" id="prov_for_district" class="form-select" required></select>
            </div>
            <div class="col-12">
              <label class="form-label">District Name</label>
              <input name="district_name" class="form-control" required>
            </div>
            <div class="col-12"><button class="btn btn-primary">Add</button></div>
          </form>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Districts</div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-light"><tr><th>#</th><th>Province</th><th>Name</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($districts as $d): ?>
              <tr>
                <td><?php echo (int)$d['district_id']; ?></td>
                <td><?php echo htmlspecialchars($d['province_name']); ?></td>
                <td><?php echo htmlspecialchars($d['name']); ?></td>
                <td><?php echo htmlspecialchars($d['created_at']); ?></td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete district? Dependent cities will be deleted.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="type" value="district">
                    <input type="hidden" name="id" value="<?php echo (int)$d['district_id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$districts): ?><tr><td colspan="5" class="text-center py-3">No districts</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">Add City</div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_city">
            <div class="col-12">
              <label class="form-label">Province</label>
              <select id="prov_for_city" class="form-select" required></select>
            </div>
            <div class="col-12">
              <label class="form-label">District</label>
              <select name="district_id" id="dist_for_city" class="form-select" required></select>
            </div>
            <div class="col-12">
              <label class="form-label">City Name</label>
              <input name="city_name" class="form-control" required>
            </div>
            <div class="col-12"><button class="btn btn-primary">Add</button></div>
          </form>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Cities</div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-light"><tr><th>#</th><th>Province</th><th>District</th><th>Name</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($cities as $c): ?>
              <tr>
                <td><?php echo (int)$c['city_id']; ?></td>
                <td><?php echo htmlspecialchars($c['province_name']); ?></td>
                <td><?php echo htmlspecialchars($c['district_name']); ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete city?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="type" value="city">
                    <input type="hidden" name="id" value="<?php echo (int)$c['city_id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$cities): ?><tr><td colspan="6" class="text-center py-3">No cities</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function fillSelect(el, items, placeholder) {
    el.innerHTML = '';
    const ph = document.createElement('option'); ph.value=''; ph.textContent=placeholder; ph.disabled=true; ph.selected=true; el.appendChild(ph);
    items.forEach(it => { const o=document.createElement('option'); o.value = it.value; o.textContent = it.label; el.appendChild(o); });
  }
  document.addEventListener('DOMContentLoaded', () => {
    const provForDistrict = document.getElementById('prov_for_district');
    const provForCity = document.getElementById('prov_for_city');
    const distForCity = document.getElementById('dist_for_city');
    const baseUrl = window.location.pathname;

    fetch(baseUrl + '?geo=provinces').then(r=>r.json()).then(list=>{
      const mapped = list.map(x=>({value:x.province_id, label:x.name}));
      fillSelect(provForDistrict, mapped, 'Select province');
      fillSelect(provForCity, mapped, 'Select province');
    });

    provForCity.addEventListener('change', ()=>{
      const pid = encodeURIComponent(provForCity.value||'');
      fetch(baseUrl + '?geo=districts&province_id=' + pid).then(r=>r.json()).then(list=>{
        fillSelect(distForCity, list.map(x=>({value:x.district_id,label:x.name})), 'Select district');
      });
    });
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
