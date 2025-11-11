<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100;
    $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END);
      $pos = ftell($fp);
      $chunk = '';
      $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096);
        $read = $pos - $step;
        fseek($fp, $step);
        $chunk = fread($fp, $read) . $chunk;
        $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk);
      $slice = array_slice($parts, -$lines);
      $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $data;
    exit;
  }
}

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');
require_once __DIR__ . '/../../config/cache.php';

// Filters
$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$wheres = [];
$params = [];
$types = '';
if ($q !== '') {
  $wheres[] = '(package_name LIKE ? OR description LIKE ? OR package_type LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}
if (in_array($type, ['property','room'], true)) {
  if ($type === 'property') {
    $wheres[] = '(max_properties IS NOT NULL AND max_properties > 0)';
  } else {
    $wheres[] = '(max_rooms IS NOT NULL AND max_rooms > 0)';
  }
}
if (in_array($status, ['active','inactive'], true)) {
  $wheres[] = 'status = ?';
  $params[] = $status;
  $types .= 's';
}
$where_sql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

$rows = [];
$baseKey = 'admin_package_read_v1_' . http_build_query(['q'=>$q,'type'=>$type,'status'=>$status]);
$cacheKey = app_cache_ns_key('packages', $baseKey);
$cached = app_cache_get($cacheKey, 120);
if ($cached !== null) {
  $rows = $cached;
} else {
  $sql = 'SELECT * FROM packages ' . $where_sql . ' ORDER BY created_at DESC';
  if ($types !== '') {
    $stmt = db()->prepare($sql);
    if ($stmt) {
      $stmt->bind_param($types, ...$params);
      if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $res->free();
      }
      $stmt->close();
    }
  } else {
    $res = db()->query($sql);
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
  }
  app_cache_set($cacheKey, $rows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Packages</h1>
    <div class="d-flex gap-2">
      <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-5">
      <label class="form-label" for="q">Search</label>
      <input type="text" id="q" name="q" class="form-control" placeholder="name, type, description" value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="type">Type</label>
      <select id="type" name="type" class="form-select">
        <option value="">Any</option>
        <option value="property" <?= $type==='property'?'selected':'' ?>>Property</option>
        <option value="room" <?= $type==='room'?'selected':'' ?>>Room</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="status">Status</label>
      <select id="status" name="status" class="form-select">
        <option value="">Any</option>
        <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="package_read.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle table-striped table-hover">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Duration</th>
              <th>Max Adv. Count</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No packages found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['package_id'] ?></td>
                <td><?= htmlspecialchars((string)$r['package_name']) ?></td>
                <?php $typeLbl = ((int)$r['max_rooms'] > 0) ? 'Room' : 'Property'; ?>
                <td><span class="badge text-bg-secondary"><?= $typeLbl ?></span></td>
                <?php $durLbl = ((string)$r['package_type'] === 'yearly') ? 'Yearly' : 'Monthly'; ?>
                <td><?= $durLbl ?></td>
                <td><?= (int)max((int)$r['max_properties'], (int)$r['max_rooms']) ?></td>
                <td>LKR <?= number_format((float)$r['price'], 2) ?></td>
                <td>
                  <?php if (($r['status'] ?? '') === 'active'): ?>
                    <span class="badge text-bg-success">active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">inactive</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
