 <?php
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Delete Room</h1>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
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
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
        <?php foreach ($cards as $c): ?>
            <div class="col">
                <div class="card h-100">
                    <?php if (!empty($c['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($c['image_path']); ?>" class="card-img-top" alt="Room image">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                            <span class="text-muted">No image</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($c['title'] ?: 'Untitled'); ?></h5>
                        <div class="text-muted mb-3">LKR <?php echo number_format((float)$c['price_per_day'], 2); ?> / day</div>
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
            <div class="col">
                <div class="text-muted py-3">You have no rooms to delete.</div>
            </div>
        <?php endif; ?>
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
