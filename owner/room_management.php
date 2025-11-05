<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($owner_id <= 0) {
    redirect_with_message('../auth/login.php', 'Please login', 'error');
}

// AJAX: serve geo data from DB (reference tables)
if (isset($_GET['geo'])) {
    header('Content-Type: application/json');
    $type = $_GET['geo'];
    if ($type === 'provinces') {
        $rows = [];
        $res = db()->query("SELECT id AS province_id, name_en AS name FROM provinces ORDER BY name_en");
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode($rows); exit;
    } elseif ($type === 'districts') {
        $province_id = (int)($_GET['province_id'] ?? 0);
        $rows = [];
        $stmt = db()->prepare("SELECT id AS district_id, name_en AS name FROM districts WHERE province_id=? ORDER BY name_en");
        $stmt->bind_param('i', $province_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode($rows); exit;
    } elseif ($type === 'cities') {
        $district_id = (int)($_GET['district_id'] ?? 0);
        $rows = [];
        $stmt = db()->prepare("SELECT id AS city_id, name_en AS name FROM cities WHERE district_id=? ORDER BY name_en");
        $stmt->bind_param('i', $district_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
        echo json_encode($rows); exit;
    }
    echo json_encode([]); exit;
}

$error = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$flash = '';
$type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $flash = 'Invalid request';
        $type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $rid = (int)($_POST['room_id'] ?? 0);
            if ($rid > 0) {
                $del = db()->prepare("DELETE FROM rooms WHERE room_id=? AND owner_id=?");
                $del->bind_param('ii', $rid, $owner_id);
                if ($del->execute() && $del->affected_rows > 0) {
                    $flash = 'Room deleted';
                    $type = 'success';
                } else {
                    $flash = 'Delete failed';
                    $type = 'error';
                }
                $del->close();
            } else {
                $flash = 'Bad input';
                $type = 'error';
            }
        } elseif ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $room_type = $_POST['room_type'] ?? 'other';
            $description = trim($_POST['description'] ?? '');
            $beds = (int)($_POST['beds'] ?? 1);
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $status = 'pending';
            $allowed_types = ['single','double','suite','dorm','other'];
            if (!in_array($room_type, $allowed_types, true)) { $room_type = 'other'; }
            // Location fields (FK IDs)
            $province_id = (int)($_POST['province_id'] ?? 0);
            $district_id = (int)($_POST['district_id'] ?? 0);
            $city_id = (int)($_POST['city_id'] ?? 0);
            $address = trim($_POST['address'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');

            if ($title !== '' && $price_per_day >= 0 && $beds >= 0 && $province_id > 0 && $district_id > 0 && $city_id > 0 && $postal_code !== '') {
                $ins = db()->prepare("INSERT INTO rooms (owner_id, title, room_type, description, beds, price_per_day, status) VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param('isssids', $owner_id, $title, $room_type, $description, $beds, $price_per_day, $status);
                if ($ins->execute()) {
                    $new_room_id = db()->insert_id;
                    // Insert location linked to room with FK IDs
                    $loc = db()->prepare('INSERT INTO locations (room_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                    $loc->bind_param('iiiiss', $new_room_id, $province_id, $district_id, $city_id, $address, $postal_code);
                    $loc->execute();
                    $loc->close();
                    // Upload primary room image (optional)
                    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                        $dir = dirname(__DIR__) . '/uploads/rooms';
                        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $fname = 'room_' . $new_room_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                        $dest = $dir . '/' . $fname;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                            $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                            $ok = false;
                            $imgIns = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)');
                            if ($imgIns) {
                                $imgIns->bind_param('is', $new_room_id, $rel);
                                $ok = $imgIns->execute();
                                $imgIns->close();
                            }
                            if (!$ok) {
                                $fallback = db()->prepare('INSERT INTO room_images (room_id, image_path) VALUES (?, ?)');
                                if ($fallback) {
                                    $fallback->bind_param('is', $new_room_id, $rel);
                                    $fallback->execute();
                                    $fallback->close();
                                }
                            }
                        }
                    }
                    // Upload gallery images (non-primary)
                    if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                        $count = count($_FILES['gallery_images']['name']);
                        for ($i=0; $i < $count; $i++) {
                            if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                            $dir = dirname(__DIR__) . '/uploads/rooms';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $fname = 'room_' . $new_room_id . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                                $ok2 = false;
                                $imgIns = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)');
                                if ($imgIns) {
                                    $imgIns->bind_param('is', $new_room_id, $rel);
                                    $ok2 = $imgIns->execute();
                                    $imgIns->close();
                                }
                                if (!$ok2) {
                                    $fallback2 = db()->prepare('INSERT INTO room_images (room_id, image_path) VALUES (?, ?)');
                                    if ($fallback2) {
                                        $fallback2->bind_param('is', $new_room_id, $rel);
                                        $fallback2->execute();
                                        $fallback2->close();
                                    }
                                }
                            }
                        }
                    }
                    // Upload payment slip (required)
                    if (!empty($_FILES['payment_slip']['name']) && is_uploaded_file($_FILES['payment_slip']['tmp_name'])) {
                        $dir2 = dirname(__DIR__) . '/uploads/room_slips';
                        if (!is_dir($dir2)) { @mkdir($dir2, 0775, true); }
                        $ext2 = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                        $fname2 = 'rps_' . $new_room_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext2);
                        $dest2 = $dir2 . '/' . $fname2;
                        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $dest2)) {
                            $rel2 = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/room_slips/' . $fname2;
                            $slipIns = db()->prepare('INSERT INTO room_payment_slips (room_id, slip_path) VALUES (?, ?)');
                            $slipIns->bind_param('is', $new_room_id, $rel2);
                            $slipIns->execute();
                            $slipIns->close();
                        }
                    }
                    $flash = 'Room submitted. Awaiting admin approval.';
                    $type = 'success';
                } else {
                    $flash = 'Failed to add room';
                    $type = 'error';
                }
                $ins->close();
            } else {
                $flash = 'Please fill all required fields (including location)';
                $type = 'error';
            }
        }
    }
}

// Load rooms for this owner
$rooms = [];
$rs = db()->prepare(
    "SELECT r.room_id, r.title, r.room_type, r.beds, r.status, r.price_per_day, r.created_at,
            (
              SELECT ri.image_path FROM room_images ri
              WHERE ri.room_id = r.room_id
              ORDER BY ri.is_primary DESC, ri.image_id DESC
              LIMIT 1
            ) AS image_path,
            pr.name_en AS province_name, d.name_en AS district_name, c.name_en AS city_name
     FROM rooms r
     LEFT JOIN locations l ON l.room_id = r.room_id
     LEFT JOIN provinces pr ON pr.id = l.province_id
     LEFT JOIN districts d ON d.id = l.district_id
     LEFT JOIN cities c ON c.id = l.city_id
     WHERE r.owner_id = ?
     ORDER BY r.room_id DESC"
);
$rs->bind_param('i', $owner_id);
$rs->execute();
$rres = $rs->get_result();
while ($row = $rres->fetch_assoc()) { $rooms[] = $row; }
$rs->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner - Room Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0">Room Management</h1>
    </div>
    <?php if ($flash): ?>
      <div class="alert alert-<?php echo $type === 'error' ? 'danger' : 'success'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="row g-4">
      <div class="col-12 col-lg-5">
        <div class="card">
          <div class="card-header">Add Room</div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="create">
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input name="title" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Room Type</label>
                <select name="room_type" class="form-select">
                  <option value="single">Single</option>
                  <option value="double">Double</option>
                  <option value="suite">Suite</option>
                  <option value="dorm">Dorm</option>
                  <option value="other" selected>Other</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" rows="3" class="form-control"></textarea>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Province</label>
                  <select name="province_id" id="province" class="form-select" required></select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">District</label>
                  <select name="district_id" id="district" class="form-select" required></select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <select name="city_id" id="city" class="form-select" required></select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Postal Code</label>
                  <input name="postal_code" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Address (optional)</label>
                  <input name="address" class="form-control">
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col">
                  <label class="form-label">Price per day (LKR)</label>
                  <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required>
                </div>
                <div class="col">
                  <label class="form-label">Beds</label>
                  <input name="beds" type="number" min="0" value="1" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Primary Image</label>
                <input type="file" name="image" accept="image/*" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Gallery Images</label>
                <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
              </div>
              <div class="mb-3">
                <label class="form-label">Room Image</label>
                <input type="file" name="image_legacy" accept="image/*" class="form-control d-none">
              </div>
              <div class="mb-3">
                <label class="form-label">Payment Slip</label>
                <input type="file" name="payment_slip" accept="image/*,application/pdf" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary">Add Room</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">Your Rooms</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Image</th>
                    <th>Title & Type</th>
                    <th>Location</th>
                    <th>Price/day</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rooms as $r): ?>
                    <?php
                      $img = $r['image_path'] ?? '';
                      if ($img && !preg_match('#^https?://#i', $img) && $img[0] !== '/') { $img = '/' . ltrim($img, '/'); }
                      $loc = trim(implode(', ', array_filter([($r['city_name'] ?? ''), ($r['district_name'] ?? ''), ($r['province_name'] ?? '')])));
                      $s = strtolower(trim((string)($r['status'] ?? '')));
                      $badge = 'bg-secondary';
                      if ($s === 'available') $badge = 'bg-success';
                      elseif ($s === 'pending') $badge = 'bg-warning text-dark';
                      elseif ($s === 'rented' || $s === 'unavailable') $badge = 'bg-danger';
                    ?>
                    <tr>
                      <td style="width: 72px;">
                        <?php if ($img): ?>
                          <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="rounded" style="width:64px;height:48px;object-fit:cover;">
                        <?php else: ?>
                          <div class="bg-light border rounded d-inline-block" style="width:64px;height:48px;"></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="fw-semibold mb-0"><?php echo htmlspecialchars($r['title']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars(ucfirst($r['room_type'] ?? '')); ?> • Beds: <?php echo (int)$r['beds']; ?></div>
                      </td>
                      <td class="text-muted small"><?php echo $loc ? htmlspecialchars($loc) : '-'; ?></td>
                      <td class="fw-semibold"><?php echo number_format((float)$r['price_per_day'], 2); ?></td>
                      <td><span class="badge <?php echo $badge; ?> text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
                      <td class="text-muted small"><?php echo htmlspecialchars($r['created_at']); ?></td>
                      <td class="text-nowrap">
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this room?');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="room_id" value="<?php echo (int)$r['room_id']; ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$rooms): ?>
                    <tr><td colspan="7" class="text-center py-4">No rooms yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function fillSelect(select, items, placeholder) {
      select.innerHTML = '';
      const ph = document.createElement('option');
      ph.value = '';
      ph.textContent = placeholder;
      ph.disabled = true; ph.selected = true;
      select.appendChild(ph);
      items.forEach(item => { const o = document.createElement('option'); if (typeof item === 'object') { o.value=item.value; o.textContent=item.label; } else { o.value=item; o.textContent=item; } select.appendChild(o); });
    }
    document.addEventListener('DOMContentLoaded', () => {
      const provSel = document.getElementById('province');
      const distSel = document.getElementById('district');
      const citySel = document.getElementById('city');
      const baseUrl = window.location.pathname;
      // provinces returns [{province_id,name}]
      fetch(baseUrl + '?geo=provinces').then(r=>r.json()).then(list=>fillSelect(provSel, list.map(x=>({value:x.province_id,label:x.name})), 'Select province')).catch(()=>fillSelect(provSel, [], 'Select province'));
      provSel.addEventListener('change', ()=>{
        const pid = encodeURIComponent(provSel.value||'');
        fetch(baseUrl + '?geo=districts&province_id=' + pid).then(r=>r.json()).then(list=>{ fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district'); fillSelect(citySel, [], 'Select city');}).catch(()=>{fillSelect(distSel, [], 'Select district'); fillSelect(citySel, [], 'Select city');});
      });
      distSel.addEventListener('change', ()=>{
        const did = encodeURIComponent(distSel.value||'');
        fetch(baseUrl + '?geo=cities&district_id=' + did).then(r=>r.json()).then(list=>fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city')).catch(()=>fillSelect(citySel, [], 'Select city'));
      });
    });
  </script>
</body>
</html>
