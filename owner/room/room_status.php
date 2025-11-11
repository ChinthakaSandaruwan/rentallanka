 <?php
 ini_set('display_errors', 0);
 ini_set('log_errors', 1);
 ini_set('error_log', __DIR__ . '/../../error/error.log');

 if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
   $f = __DIR__ . '/../../error/error.log';
   if (is_readable($f)) {
     $lines = 100; $data = '';
     $fp = fopen($f, 'r');
     if ($fp) {
       fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
       while ($pos > 0 && $ln <= $lines) {
         $step = max(0, $pos - 4096); $read = $pos - $step;
         fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
         $ln = substr_count($chunk, "\n");
       }
       fclose($fp);
       $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
     }
     header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
   }
 }

 require_once __DIR__ . '/../../public/includes/auth_guard.php';
 require_role('owner');
 require_once __DIR__ . '/../../config/config.php';

 $uid = (int)($_SESSION['user']['user_id'] ?? 0);
 if ($uid <= 0) {
   redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
 }

if (empty($_SESSION['csrf_room_status'])) {
  $_SESSION['csrf_room_status'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_room_status'];

$flash = '';
$flash_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($csrf, $token)) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Invalid request.', 'error');
    exit;
  }
  $room_id = (int)($_POST['room_id'] ?? 0);
  $new_status = strtolower((string)($_POST['status'] ?? ''));
  $allowed = ['available','unavailable']; // owners cannot directly set rented/pending
  if ($room_id > 0 && in_array($new_status, $allowed, true)) {
    // Fetch current status to prevent illegal transition from rented -> available
    $cur = db()->prepare('SELECT status FROM rooms WHERE room_id=? AND owner_id=?');
    $cur->bind_param('ii', $room_id, $uid);
    $cur->execute();
    $curRes = $cur->get_result()->fetch_assoc();
    $cur->close();
    $currentStatus = strtolower((string)($curRes['status'] ?? ''));
    // Allow changing to available even if currently rented; date overlap checks on booking enforce correctness.
    // Allow setting to available regardless of existing future rentals; overlap checks on booking will enforce availability per date.
    $st = db()->prepare('UPDATE rooms SET status=? WHERE room_id=? AND owner_id=?');
    $st->bind_param('sii', $new_status, $room_id, $uid);
    $ok = $st->execute();
    $aff = $st->affected_rows;
    $st->close();
    if ($ok && $aff > 0) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Status updated.', 'success');
      exit;
    }
    redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'No changes made. Make sure the room exists and belongs to you.', 'warning');
    exit;
  }
  redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_status.php', 'Invalid input.', 'error');
  exit;
}

// Load owner's rooms
$rooms = [];
$activeMap = [];
try {
  $q = db()->prepare('SELECT room_id, title, status, price_per_day, created_at FROM rooms WHERE owner_id=? ORDER BY created_at DESC');
  $q->bind_param('i', $uid);
  $q->execute();
  $r = $q->get_result();
  while ($row = $r->fetch_assoc()) { $rooms[] = $row; }
  $q->close();
  // Build map of rooms that have active rentals (booked / checked_in)
  if ($rooms) {
    $ids = array_column($rooms, 'room_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // Prepare dynamic IN clause
    $types = str_repeat('i', count($ids));
    $sql = "SELECT room_id, COUNT(*) AS c FROM room_rents WHERE status IN ('booked','checked_in') AND room_id IN ($placeholders) GROUP BY room_id";
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $activeMap[(int)$row['room_id']] = (int)$row['c']; }
    $stmt->close();
  }
} catch (Throwable $e) { $rooms = []; $activeMap = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.25rem 1.75rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.5rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1rem 1.25rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    .form-label { font-weight:600; color:var(--rl-text); margin-bottom:.5rem; font-size:.9375rem; }
    .form-control,.form-select { border:2px solid var(--rl-border); border-radius:10px; padding:.5rem .75rem; font-size:.9375rem; color:var(--rl-text); background:var(--rl-white); transition:all .2s ease; font-weight:500; }
    .form-control:focus,.form-select:focus { border-color:var(--rl-primary); box-shadow:0 0 0 3px rgba(0,78,152,.1); outline:none; }
    .btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; padding:.5rem 1rem; border-radius:10px; box-shadow:0 4px 16px rgba(0,78,152,.2); }
    .btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-1px); }

    .table thead th { background:#f8fafc; border-bottom:2px solid var(--rl-border); color:var(--rl-text-secondary); font-weight:700; font-size:.875rem; text-transform:uppercase; letter-spacing:.5px; }
    .table tbody tr { border-bottom:1px solid var(--rl-border); }
    .table tbody tr:hover { background:rgba(0,78,152,.02); }

    .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

    @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-toggles"></i> Room Status</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php /* Flash is shown globally via SweetAlert2 in navbar; removed Bootstrap alerts */ ?>

  <?php if (!$rooms): ?>
    <div class="rl-empty-state">
      <i class="bi bi-door-closed"></i>
      <p class="mb-3">You have no rooms yet.</p>
      <a href="room_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Room</a>
    </div>
  <?php else: ?>
    <div class="rl-form-card">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-list-check"></i> Manage Room Status</h2></div>
      <div class="rl-form-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Current Status</th>
                <th>Price/Day</th>
                <th>Created</th>
                <th>Change To</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rooms as $rm): ?>
                <tr>
                  <td>#<?php echo (int)$rm['room_id']; ?></td>
                  <td><?php echo htmlspecialchars($rm['title'] ?: 'Untitled'); ?></td>
                  <td>
                    <?php $st = (string)($rm['status'] ?? ''); $cls = ['available'=>'success','rented'=>'warning','unavailable'=>'secondary','pending'=>'info'][$st] ?? 'secondary'; ?>
                    <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $st ?: 'pending'))); ?></span>
                  </td>
                  <td>LKR <?php echo number_format((float)($rm['price_per_day'] ?? 0), 2); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($rm['created_at'] ?? '')))); ?></td>
                  <td>
                    <?php $hasActive = !empty($activeMap[(int)$rm['room_id'] ?? 0]); ?>
                    <form method="post" class="d-flex gap-2 align-items-center room-status-form">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="room_id" value="<?php echo (int)$rm['room_id']; ?>">
                      <select name="status" class="form-select form-select-sm" style="max-width: 180px;">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                      </select>
                      <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </form>
                  </td>
                  <td>
                    <a href="room_update.php?id=<?php echo (int)$rm['room_id']; ?>" class="btn btn-outline-secondary btn-sm">Edit Room</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.room-status-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="room_id"]').value;
          const sel = form.querySelector('select[name="status"]');
          const next = sel ? sel.value : '';
          const res = await Swal.fire({
            title: 'Change status?',
            text: 'Set room #' + rid + ' to ' + next + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, change',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
</body>
</html>
