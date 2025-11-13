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

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$property_id = (int)($_GET['property_id'] ?? 0);
$error = '';
$flash = '';
$flash_type = 'success';

// Load property
$prop = null;
if ($property_id > 0) {
  $stmt = db()->prepare("SELECT p.property_id, p.property_code, p.title, p.status, p.owner_id, u.name AS owner_name FROM properties p LEFT JOIN users u ON u.user_id=p.owner_id WHERE p.property_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $property_id);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $prop = $res->fetch_assoc();
      $res->free();
    }
    $stmt->close();
  }
}

if ($property_id > 0 && !$prop) {
  $error = 'Invalid property id';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $new_status = $_POST['status'] ?? '';
    $pid = (int)($_POST['property_id'] ?? 0);
    $allowed = ['pending','available','unavailable'];
    if (!in_array($new_status, $allowed, true)) {
      $error = 'Invalid status value';
    } elseif ($pid <= 0) {
      $error = 'Invalid property id';
    } else {
      $st = db()->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
      if ($st) {
        $st->bind_param('si', $new_status, $pid);
        if ($st->execute()) {
          $flash = 'Status updated';
          $flash_type = 'success';
          $st->close();
          $property_id = $pid;
          // Reload
          $stmt2 = db()->prepare("SELECT p.property_id, p.property_code, p.title, p.status, p.owner_id, u.name AS owner_name FROM properties p LEFT JOIN users u ON u.user_id=p.owner_id WHERE p.property_id = ? LIMIT 1");
          if ($stmt2) {
            $stmt2->bind_param('i', $property_id);
            if ($stmt2->execute()) {
              $res2 = $stmt2->get_result();
              $prop = $res2->fetch_assoc();
              $res2->free();
            }
            $stmt2->close();
          }
        } else { $error = 'Update failed'; $st->close(); }
      } else { $error = 'Update failed'; }
    }
  }
}
// POST-Redirect-GET to avoid resubmission on refresh
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $msg = $flash ?: ($error ?: 'Action completed.');
  $typ = $flash ? ($flash_type ?: 'success') : ($error ? 'error' : 'success');
  $url = rtrim($base_url,'/') . '/admin/property/property_status.php' . ($property_id>0 ? ('?property_id='.(int)$property_id) : '');
  redirect_with_message($url, $msg, $typ);
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change Property Status</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Change Property Status</h1>
      <div class="d-flex gap-2">
        <a href="property_approval.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-square me-1"></i>Approval</a>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <?php /* Alerts handled by SweetAlert2 via navbar; Bootstrap alert markup removed */ ?>

    <?php if ($prop): ?>
    <div class="card">
      <div class="card-header">Property</div>
      <div class="card-body">
        <div class="mb-3">
          <div class="fw-semibold"><?php echo htmlspecialchars(($prop['property_code'] ?? '') . ' - ' . ($prop['title'] ?? '')); ?></div>
          <div class="text-muted small">Owner: <?php echo htmlspecialchars(($prop['owner_name'] ?? 'N/A') . ' (#' . (int)($prop['owner_id'] ?? 0) . ')'); ?></div>
        </div>
        <form method="post" class="row g-3" id="formPropertyStatus">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="property_id" value="<?php echo (int)$prop['property_id']; ?>">
          <div class="col-12 col-md-6">
            <label for="status" class="form-label">Status<span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select" required>
              <?php $st = $prop['status'] ?? 'pending'; ?>
              <option value="pending" <?php echo $st==='pending'?'selected':''; ?>>Pending</option>
              <option value="available" <?php echo $st==='available'?'selected':''; ?>>Available</option>
              <option value="unavailable" <?php echo $st==='unavailable'?'selected':''; ?>>Unavailable</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a class="btn btn-outline-secondary" href="property_status.php">Back</a>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
      <?php
        // Filters and list when no specific property is selected
        $q = trim($_GET['q'] ?? '');
        $status_filter = $_GET['status'] ?? '';
        $wheres = [];
        $params = [];
        $types = '';
        if ($q !== '') {
          $wheres[] = "(p.title LIKE ? OR p.property_code LIKE ? OR u.name LIKE ?)";
          $like = '%' . $q . '%';
          $params[] = $like; $params[] = $like; $params[] = $like;
          $types .= 'sss';
        }
        if (in_array($status_filter, ['pending','available','unavailable'], true)) {
          $wheres[] = 'p.status = ?';
          $params[] = $status_filter;
          $types .= 's';
        }
        $where_sql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';
        $sql = 'SELECT p.property_id, p.property_code, p.title, p.status, p.owner_id, u.name AS owner_name '
             . 'FROM properties p LEFT JOIN users u ON u.user_id = p.owner_id '
             . $where_sql . ' ORDER BY p.property_id DESC';
        $props = [];
        if ($types !== '') {
          $stmtL = db()->prepare($sql);
          if ($stmtL) {
            $stmtL->bind_param($types, ...$params);
            if ($stmtL->execute()) {
              $resL = $stmtL->get_result();
              while ($row = $resL->fetch_assoc()) { $props[] = $row; }
              $resL->free();
            }
            $stmtL->close();
          }
        } else {
          $result = db()->query($sql);
          if ($result) {
            while ($row = $result->fetch_assoc()) { $props[] = $row; }
            $result->close();
          }
        }
      ?>
      <div class="card">
        <div class="card-header">Properties</div>
        <div class="card-body p-0">
          <form method="get" class="p-3 border-bottom bg-light">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-6">
                <label class="form-label" for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="title, code, owner name" value="<?php echo htmlspecialchars($q ?? ''); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                  <option value="">Any</option>
                  <option value="pending" <?php echo ($status_filter==='pending')?'selected':''; ?>>Pending</option>
                  <option value="available" <?php echo ($status_filter==='available')?'selected':''; ?>>Available</option>
                  <option value="unavailable" <?php echo ($status_filter==='unavailable')?'selected':''; ?>>Unavailable</option>
                </select>
              </div>
              <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="property_status.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
              </div>
            </div>
          </form>
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 80px;">ID</th>
                  <th>Code</th>
                  <th>Title</th>
                  <th>Owner</th>
                  <th style="width: 120px;">Status</th>
                  <th style="width: 160px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($props)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">No properties found.</td></tr>
                <?php else: ?>
                  <?php foreach ($props as $p): ?>
                    <tr>
                      <td><?php echo (int)$p['property_id']; ?></td>
                      <td><?php echo htmlspecialchars($p['property_code'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($p['title'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars(($p['owner_name'] ?? 'N/A') . ' (#' . (int)($p['owner_id'] ?? 0) . ')'); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $p['status']==='available'?'success':($p['status']==='pending'?'secondary':'danger'); ?>">
                          <?php echo htmlspecialchars($p['status']); ?>
                        </span>
                      </td>
                      <td>
                        <a class="btn btn-sm btn-outline-primary" href="property_status.php?property_id=<?php echo (int)$p['property_id']; ?>">
                          <i class="bi bi-arrow-repeat me-1"></i>Change Status
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script>
    (function(){
      try {
        const form = document.getElementById('formPropertyStatus');
        if (form) {
          form.addEventListener('submit', async function(e){
            e.preventDefault();
            const sel = form.querySelector('#status');
            const to = sel ? sel.options[sel.selectedIndex].textContent.trim() : '';
            const res = await Swal.fire({
              title: 'Change status?',
              text: to ? ('Change property status to ' + to + '?') : 'Change property status?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, change',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          });
        }
      } catch(_) {}
    })();
  </script>
</body>
</html>
