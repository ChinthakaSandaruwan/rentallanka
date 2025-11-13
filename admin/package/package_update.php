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

$alert = ['type' => '', 'msg' => ''];
$package = null;
$pid = (int)($_GET['package_id'] ?? 0);

if ($pid > 0) {
  try {
    $st = db()->prepare('SELECT * FROM packages WHERE package_id=? LIMIT 1');
    $st->bind_param('i', $pid);
    $st->execute();
    $package = $st->get_result()->fetch_assoc();
    $st->close();
  } catch (Throwable $e) {
    $package = null;
  }
}

$is_post = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
if ($is_post) {
  try {
    $pid = (int)($_POST['package_id'] ?? 0);
    if ($pid <= 0) { throw new Exception('Invalid package id'); }
    $package_name = trim((string)($_POST['package_name'] ?? ''));
    $type_choice = trim((string)($_POST['type_choice'] ?? 'property')); // property | room
    $duration_choice = trim((string)($_POST['duration_choice'] ?? 'monthly')); // monthly | yearly
    $max_count = (int)($_POST['max_count'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = (string)($_POST['description'] ?? '');
    $status = trim((string)($_POST['status'] ?? 'active'));

    if ($package_name === '') { throw new Exception('Package name required'); }
    if (!in_array($type_choice, ['property','room'], true)) { throw new Exception('Invalid type'); }
    if (!in_array($duration_choice, ['monthly','yearly'], true)) { throw new Exception('Invalid duration'); }
    if ($max_count < 0) { throw new Exception('Max Advertising Count must be >= 0'); }
    $allowed_status = ['active','inactive'];
    if (!in_array($status, $allowed_status, true)) { throw new Exception('Invalid status'); }

    $package_type = $duration_choice; // monthly|yearly
    $duration_days = ($duration_choice === 'monthly') ? 30 : 365;
    $max_properties = ($type_choice === 'property') ? $max_count : 0;
    $max_rooms = ($type_choice === 'room') ? $max_count : 0;

    $stmt = db()->prepare('UPDATE packages SET package_name=?, package_type=?, duration_days=?, max_properties=?, max_rooms=?, price=?, description=?, status=? WHERE package_id=?');
    if (!$stmt) { throw new Exception('DB error'); }
    $stmt->bind_param('ssiiidssi', $package_name, $package_type, $duration_days, $max_properties, $max_rooms, $price, $description, $status, $pid);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) { throw new Exception('Update failed'); }
    // PRG redirect so refresh doesn't resubmit; flash handled by navbar (SweetAlert2)
    redirect_with_message(rtrim($base_url,'/') . '/admin/package/package_update.php?package_id=' . (int)$pid, 'Package updated', 'success');
    exit;
  } catch (Throwable $e) {
    $alert = ['type' => 'danger', 'msg' => htmlspecialchars($e->getMessage())];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Update Package</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Update Package</h1>
    <div class="d-flex gap-2">
      <a href="packages_management.php" class="btn btn-outline-secondary btn-sm">All Packages</a>
      <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <?php /* Alerts handled via SweetAlert2 below; Bootstrap alerts removed */ ?>

  <?php if (!$package): ?>
    <?php
      // List packages with filters
      $q = trim((string)($_GET['q'] ?? ''));
      $type = trim((string)($_GET['type'] ?? ''));
      $statusF = trim((string)($_GET['status'] ?? ''));

      $wheres = [];
      $params = [];
      $types = '';
      if ($q !== '') {
        $wheres[] = '(package_name LIKE ? OR description LIKE ? OR package_type LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
      }
      if (in_array($type, ['property','room'], true)) {
        if ($type === 'property') { $wheres[] = '(max_properties IS NOT NULL AND max_properties > 0)'; }
        else { $wheres[] = '(max_rooms IS NOT NULL AND max_rooms > 0)'; }
      }
      if (in_array($statusF, ['active','inactive'], true)) {
        $wheres[] = 'status = ?'; $params[] = $statusF; $types .= 's';
      }
      $where_sql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';
      $rows = [];
      $sql = 'SELECT * FROM packages ' . $where_sql . ' ORDER BY created_at DESC';
      if ($types !== '') {
        $stmtL = db()->prepare($sql);
        if ($stmtL) {
          $stmtL->bind_param($types, ...$params);
          if ($stmtL->execute()) { $resL = $stmtL->get_result(); while ($r = $resL->fetch_assoc()) { $rows[] = $r; } $resL->free(); }
          $stmtL->close();
        }
      } else {
        $res = db()->query($sql);
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
      }
    ?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-3">All Packages</h5>
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
              <option value="active" <?= $statusF==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $statusF==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="package_update.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table align-middle table-striped table-hover mb-0">
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
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No packages found.</td></tr>
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
                  <td class="text-end">
                    <a href="package_update.php?package_id=<?= (int)$r['package_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php else: ?>
  <div class="card">
    <div class="card-body">
      <form method="post" class="row g-3 needs-validation" novalidate id="formPackageUpdate">
        <input type="hidden" name="package_id" value="<?= (int)$package['package_id'] ?>">
        <div class="col-12 col-md-6">
          <label for="package_name" class="form-label">Package Name</label>
          <input type="text" id="package_name" name="package_name" class="form-control" value="<?= htmlspecialchars((string)($package['package_name'] ?? '')) ?>" maxlength="120" required>
          <div class="invalid-feedback">Package name is required.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="type_choice" class="form-label">Type</label>
          <?php $typeGuess = ((int)($package['max_rooms'] ?? 0) > 0) ? 'room' : 'property'; ?>
          <select id="type_choice" name="type_choice" class="form-select" required>
            <option value="property" <?= $typeGuess==='property'?'selected':'' ?>>Property</option>
            <option value="room" <?= $typeGuess==='room'?'selected':'' ?>>Room</option>
          </select>
          <div class="invalid-feedback">Please select a type.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="duration_choice" class="form-label">Duration</label>
          <?php $durGuess = (string)($package['package_type'] ?? 'monthly'); ?>
          <select id="duration_choice" name="duration_choice" class="form-select" required>
            <option value="monthly" <?= $durGuess==='monthly'?'selected':'' ?>>Monthly</option>
            <option value="yearly" <?= $durGuess==='yearly'?'selected':'' ?>>Yearly</option>
          </select>
          <div class="invalid-feedback">Please select a duration.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="max_count" class="form-label">Max Advertising Count</label>
          <?php $maxCountGuess = (int)max((int)($package['max_properties'] ?? 0),(int)($package['max_rooms'] ?? 0)); ?>
          <input type="number" id="max_count" name="max_count" class="form-control" value="<?= $maxCountGuess ?>" min="0" step="1" required>
          <div class="invalid-feedback">Please enter a valid max count (0 or more).</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="price" class="form-label">Price (LKR)</label>
          <input type="number" id="price" step="0.01" min="0" name="price" class="form-control" value="<?= htmlspecialchars((string)($package['price'] ?? '0.00')) ?>" required>
          <div class="invalid-feedback">Please enter a valid price.</div>
        </div>
        <div class="col-12 col-md-4">
          <label for="status" class="form-label">Status</label>
          <?php $st = (string)($package['status'] ?? 'active'); ?>
          <select id="status" name="status" class="form-select" required>
            <option value="active" <?= $st==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $st==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
          <div class="invalid-feedback">Please select a status.</div>
        </div>
        <div class="col-12">
          <label for="description" class="form-label">Description</label>
          <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars((string)($package['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <a href="packages_management.php" class="btn btn-outline-secondary">Cancel</a>
          <button class="btn btn-primary" type="submit">Update</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
  (function(){
    try {
      const msg = <?= json_encode($alert['msg']) ?>;
      const typ = (<?= json_encode($alert['type']) ?> || '').toLowerCase();
      if (msg) {
        const icon = ({ success:'success', danger:'error', error:'error', warning:'warning', info:'info' })[typ] || 'error';
        Swal.fire({ icon, title: icon==='success'?'Success':icon==='warning'?'Warning':icon==='info'?'Info':'Error', text: String(msg), confirmButtonText: 'OK' });
      }
      const form = document.getElementById('formPackageUpdate');
      if (form) {
        form.addEventListener('submit', async function(e){
          if (!form.checkValidity()) return;
          e.preventDefault();
          const name = (form.querySelector('#package_name')?.value || '').trim();
          const res = await Swal.fire({
            title: 'Save changes?',
            text: name ? ('Update package "' + name + '"?') : 'Update this package?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, update',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      }
    } catch(_) {}
  })();
</script>
<script>
  (() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>
</body>
</html>
