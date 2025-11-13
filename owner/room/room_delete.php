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
 require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
   redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$room_id = (int)($_GET['id'] ?? $_POST['room_id'] ?? 0);
$room = null;
$flash = '';
$flash_type = '';
$error = '';

if ($room_id > 0) {
    $stmt = db()->prepare('SELECT r.room_id, r.title, r.price_per_day FROM rooms r WHERE r.room_id=? AND r.owner_id=?');
    $stmt->bind_param('ii', $room_id, $uid);
    $stmt->execute();
    $rs = $stmt->get_result();
    $room = $rs->fetch_assoc() ?: null;
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        // Reload room ensuring ownership using POSTed room_id
        $room_id = (int)($_POST['room_id'] ?? 0);
        $room = null;
        if ($room_id > 0) {
            $stmt = db()->prepare('SELECT r.room_id, r.title, r.price_per_day FROM rooms r WHERE r.room_id=? AND r.owner_id=?');
            $stmt->bind_param('ii', $room_id, $uid);
            $stmt->execute();
            $rs = $stmt->get_result();
            $room = $rs->fetch_assoc() ?: null;
            $stmt->close();
        }
        if (!$room) {
            redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_delete.php', 'Room not found', 'error');
        }
        $imgs = [];
        $gi = db()->prepare('SELECT image_path FROM room_images WHERE room_id=?');
        $gi->bind_param('i', $room_id);
        $gi->execute();
        $ir = $gi->get_result();
        while ($row = $ir->fetch_assoc()) {
            $imgs[] = $row['image_path'];
        }
        $gi->close();

        db()->begin_transaction();
        try {
            // Delete dependent rows first to satisfy foreign keys
            $dl = db()->prepare('DELETE FROM room_locations WHERE room_id=?');
            $dl->bind_param('i', $room_id);
            $dl->execute();
            $dl->close();

            // Optional per-room meal prices
            try {
                $dm = db()->prepare('DELETE FROM room_meals WHERE room_id=?');
                $dm->bind_param('i', $room_id);
                $dm->execute();
                $dm->close();
            } catch (Throwable $e) { /* ignore */ }

            $di = db()->prepare('DELETE FROM room_images WHERE room_id=?');
            $di->bind_param('i', $room_id);
            $di->execute();
            $di->close();

            $dr = db()->prepare('DELETE FROM rooms WHERE room_id=? AND owner_id=?');
            $dr->bind_param('ii', $room_id, $uid);
            $dr->execute();
            $affected = $dr->affected_rows;
            $dr->close();

            if ($affected <= 0) {
                throw new Exception('Delete failed');
            }

            db()->commit();

            $prefix = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/';
            $root = dirname(__DIR__, 2) . '/uploads/rooms/';
            foreach ($imgs as $path) {
                if (!$path) continue;
                if (strpos($path, $prefix) === 0) {
                    $fname = substr($path, strlen($prefix));
                    $full = $root . $fname;
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
            }

            redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_delete.php', 'Room deleted successfully.', 'success');
        } catch (Throwable $e) {
            db()->rollback();
            redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_delete.php', 'Failed to delete room.', 'error');
        }
    }
}

[$flash, $flash_type] = [$flash ?: (get_flash()[0] ?? ''), $flash_type ?: (get_flash()[1] ?? '')];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Room</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
      :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
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

      .room-card { position:relative; transition:all .3s cubic-bezier(.4,0,.2,1); }
      .room-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .25s ease; }
      .room-card:hover { transform:translateY(-6px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent) !important; }
      .room-card:hover::before { opacity:1; }
      .card-img-top { object-fit:cover; height: 200px; }
      .placeholder-img { background:linear-gradient(135deg,#eef2f7 0%, #e2e8f0 100%); color:#64748b; height:200px; }

      .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
      .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

      @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } .card-img-top,.placeholder-img{ height:180px; } }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
    <div class="rl-page-header">
        <h1 class="rl-page-title"><i class="bi bi-trash3"></i> Delete Room</h1>
        <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <?php /* Flash and errors are shown via global SweetAlert2 (navbar); removed Bootstrap alerts */ ?>

    <?php
    $cards = [];
    $q = db()->prepare('SELECT r.room_id, r.title, r.price_per_day, (SELECT image_path FROM room_images WHERE room_id=r.room_id AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1) AS image_path FROM rooms r WHERE r.owner_id=? ORDER BY r.created_at DESC');
    $q->bind_param('i', $uid);
    $q->execute();
    $rr = $q->get_result();
    while ($row = $rr->fetch_assoc()) {
        $cards[] = $row;
    }
    $q->close();
    ?>
    <div class="rl-form-card">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-card-list"></i> Select a Room to Delete</h2></div>
      <div class="rl-form-body">
        <div class="row g-3">
        <?php foreach ($cards as $c): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="rl-form-card h-100 room-card">
                    <?php if (!empty($c['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($c['image_path']); ?>" class="card-img-top" alt="Room image">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center placeholder-img">
                            <span>No image</span>
                        </div>
                    <?php endif; ?>
                    <div class="rl-form-body d-flex flex-column">
                        <h5 class="mb-1" style="font-weight:800; color:var(--rl-text);"><?php echo htmlspecialchars($c['title'] ?: 'Untitled'); ?></h5>
                        <div class="mb-3" style="font-weight:800; color:var(--rl-dark);">LKR <?php echo number_format((float)$c['price_per_day'], 2); ?> / day</div>
                        <div class="mt-auto">
                            <form method="post" class="d-inline room-del-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="room_id" value="<?php echo (int)$c['room_id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($cards)): ?>
            <div class="col-12">
                <div class="rl-empty-state">
                  <i class="bi bi-door-closed"></i>
                  <p class="mb-3">You have no rooms to delete.</p>
                  <a href="room_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Room</a>
                </div>
            </div>
        <?php endif; ?>
        </div>
      </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.room-del-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const titleEl = form.closest('.card')?.querySelector('.card-title');
          const title = titleEl ? titleEl.textContent.trim() : 'this room';
          const res = await Swal.fire({
            title: 'Delete room?',
            text: 'This action cannot be undone. Delete ' + title + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
<script src="js/room_delete.js" defer></script>
</body>
</html>
