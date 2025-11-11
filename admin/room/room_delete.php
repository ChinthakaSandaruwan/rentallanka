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
?>
<?php require_once __DIR__ . '/../../public/includes/auth_guard.php'; require_role('admin'); ?>
<?php require_once __DIR__ . '/../../config/config.php'; ?>
<?php
// CSRF token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$room = null;
$room_id = (int)($_GET['room_id'] ?? 0);

// Load room details when id provided
if ($room_id > 0) {
  $st = db()->prepare('SELECT * FROM rooms WHERE room_id = ?');
  if ($st) {
    $st->bind_param('i', $room_id);
    if ($st->execute()) {
      $res = $st->get_result();
      $room = $res ? $res->fetch_assoc() : null;
      if ($res) { $res->free(); }
    }
    $st->close();
  }
}

// Handle deletion (PRG)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { throw new Exception('Invalid CSRF token'); }
    $rid = (int)($_POST['room_id'] ?? 0);
    if ($rid <= 0) { throw new Exception('Invalid room id'); }
    $st = db()->prepare('DELETE FROM rooms WHERE room_id = ?');
    if (!$st) { throw new Exception('Failed to prepare'); }
    $st->bind_param('i', $rid);
    if (!$st->execute()) { throw new Exception('Failed to delete'); }
    $affected = db()->affected_rows;
    $st->close();
    $msg = ($affected > 0) ? 'Room deleted successfully.' : 'No room deleted. It may not exist.';
    $typ = ($affected > 0) ? 'success' : 'warning';
    redirect_with_message(rtrim($base_url,'/') . '/admin/room/room_delete.php', $msg, $typ);
    exit;
  } catch (Throwable $e) {
    redirect_with_message(rtrim($base_url,'/') . '/admin/room/room_delete.php', 'Error: ' . ($e->getMessage() ?: 'Delete failed'), 'error');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Room</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Delete Room</h1>
    <div class="d-flex gap-2">
      <a href="../room.php" class="btn btn-outline-secondary btn-sm">Rooms</a>
      <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <?php /* Alerts handled by SweetAlert2 via navbar; Bootstrap alerts removed */ ?>

  <?php if ($room_id <= 0 || !$room): ?>
    <?php
      // List rooms (up to 100) with search by ID or title, and optional status filter
      $rq = trim((string)($_GET['q'] ?? ''));
      $rs = trim((string)($_GET['status'] ?? ''));
      $rooms = [];
      try {
        $where = [];
        $params = [];
        $types = '';
        if ($rq !== '') {
          if (ctype_digit($rq)) { $where[] = 'room_id = ?'; $params[] = (int)$rq; $types .= 'i'; }
          else { $where[] = 'title LIKE ?'; $params[] = '%' . $rq . '%'; $types .= 's'; }
        }
        if (in_array($rs, ['active','inactive','pending'], true)) { $where[] = 'status = ?'; $params[] = $rs; $types .= 's'; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT * FROM rooms $whereSql ORDER BY room_id DESC LIMIT 100";
        if ($types !== '') {
          $stL = db()->prepare($sql);
          if ($stL) {
            $stL->bind_param($types, ...$params);
            if ($stL->execute()) { $res = $stL->get_result(); while ($r = $res->fetch_assoc()) { $rooms[] = $r; } if ($res) { $res->free(); } }
            $stL->close();
          }
        } else {
          $res = db()->query($sql);
          if ($res) { while ($r = $res->fetch_assoc()) { $rooms[] = $r; } }
        }
      } catch (Throwable $e) { $rooms = []; }
    ?>
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">All Rooms</h5>
          <form method="get" class="row g-2 align-items-center">
            <div class="col-auto">
              <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($rq) ?>" placeholder="Search by ID or title">
            </div>
            <div class="col-auto">
              <select name="status" class="form-select">
                <option value="">Any status</option>
                <option value="active" <?= $rs==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $rs==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="pending" <?= $rs==='pending'?'selected':'' ?>>Pending</option>
              </select>
            </div>
            <div class="col-auto d-flex gap-2">
              <button class="btn btn-outline-secondary" type="submit">Search</button>
              <a href="room_delete.php" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rooms): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No rooms found.</td></tr>
              <?php else: foreach ($rooms as $r): ?>
                <tr>
                  <td><?= (int)($r['room_id'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string)($r['title'] ?? '—')) ?></td>
                  <td>
                    <?php $st = (string)($r['status'] ?? ''); ?>
                    <?php if ($st !== ''): ?>
                      <span class="badge <?= $st==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= htmlspecialchars($st) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline rd-del-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="room_id" value="<?= (int)($r['room_id'] ?? 0) ?>">
                      <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
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
        <h5 class="card-title mb-3">Confirm Delete</h5>
        <div class="mb-3">
          <div class="text-muted small">Room ID</div>
          <div class="fw-semibold">#<?= (int)$room['room_id'] ?></div>
        </div>
        <?php if (!empty($room['title'] ?? '')): ?>
        <div class="mb-3">
          <div class="text-muted small">Title</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)$room['title']) ?></div>
        </div>
        <?php endif; ?>
        <div class="text-muted small mb-2">This action cannot be undone.</div>
        <form method="post" class="d-flex gap-2" id="formDeleteConfirm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="room_id" value="<?= (int)$room['room_id'] ?>">
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete Room</button>
          <a href="room_delete.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
  (function(){
    try {
      // List view delete confirm
      document.querySelectorAll('form.rd-del-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const id = form.querySelector('input[name="room_id"]').value;
          const res = await Swal.fire({
            title: 'Delete room #' + id + '?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
      // Detail confirm
      const dc = document.getElementById('formDeleteConfirm');
      if (dc) {
        dc.addEventListener('submit', async function(e){
          e.preventDefault();
          const id = dc.querySelector('input[name="room_id"]').value;
          const res = await Swal.fire({
            title: 'Delete room #' + id + '?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { dc.submit(); }
        });
      }
    } catch(_) {}
  })();
</script>
</body>
</html>
