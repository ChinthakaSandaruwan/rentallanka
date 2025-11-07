<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($owner_id <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/room_management.php', 'Invalid room', 'error');
}

$q = db()->prepare("SELECT r.*, l.province_id, l.district_id, l.city_id, l.address, l.postal_code FROM rooms r LEFT JOIN locations l ON l.room_id=r.room_id WHERE r.room_id=? AND r.owner_id=? LIMIT 1");
$q->bind_param('ii', $rid, $owner_id);
$q->execute();
$room = $q->get_result()->fetch_assoc();
$q->close();
if (!$room) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/room_management.php', 'Room not found', 'error');
}

$can_edit = (strtotime((string)$room['created_at']) + 24*3600) > time();
if (!$can_edit) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/room_management.php', 'Editing locked after 24 hours', 'error');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? 'update';
        if ($action === 'upload_images') {
            if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                $count = count($_FILES['gallery_images']['name']);
                for ($i=0; $i<$count; $i++) {
                    if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                    $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                    $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                    if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                    $dir = dirname(__DIR__) . '/uploads/rooms';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                    $fname = 'room_' . $rid . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                    $dest = $dir . '/' . $fname;
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                        $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                        $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)');
                        if ($pi) { $pi->bind_param('is', $rid, $rel); $pi->execute(); $pi->close(); }
                    }
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/room_edit.php?id='.(int)$rid, 'Images uploaded', 'success');
        } elseif ($action === 'set_primary') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            if ($image_id > 0) {
                $chk = db()->prepare('SELECT image_path FROM room_images WHERE image_id=? AND room_id=?');
                $chk->bind_param('ii', $image_id, $rid);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row) {
                    $clr = db()->prepare('UPDATE room_images SET is_primary=0 WHERE room_id=?');
                    $clr->bind_param('i', $rid);
                    $clr->execute();
                    $clr->close();
                    $sp = db()->prepare('UPDATE room_images SET is_primary=1 WHERE image_id=? AND room_id=?');
                    $sp->bind_param('ii', $image_id, $rid);
                    $sp->execute();
                    $sp->close();
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/room_edit.php?id='.(int)$rid, 'Primary image updated', 'success');
        } elseif ($action === 'delete_image') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            if ($image_id > 0) {
                $chk = db()->prepare('SELECT image_path, is_primary FROM room_images WHERE image_id=? AND room_id=?');
                $chk->bind_param('ii', $image_id, $rid);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row) {
                    $del = db()->prepare('DELETE FROM room_images WHERE image_id=? AND room_id=?');
                    $del->bind_param('ii', $image_id, $rid);
                    $del->execute();
                    $del->close();
                    if ((int)$row['is_primary'] === 1) {
                        $nx = db()->prepare('SELECT image_id FROM room_images WHERE room_id=? ORDER BY is_primary DESC, image_id DESC LIMIT 1');
                        $nx->bind_param('i', $rid);
                        $nx->execute();
                        $nrow = $nx->get_result()->fetch_assoc();
                        $nx->close();
                        if ($nrow) {
                            $sp = db()->prepare('UPDATE room_images SET is_primary=1 WHERE image_id=?');
                            $sp->bind_param('i', $nrow['image_id']);
                            $sp->execute();
                            $sp->close();
                        }
                    }
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/room_edit.php?id='.(int)$rid, 'Image deleted', 'success');
        } else {
            $title = trim($_POST['title'] ?? '');
            $room_type = $_POST['room_type'] ?? 'other';
            $description = trim($_POST['description'] ?? '');
            $beds = (int)($_POST['beds'] ?? 1);
            $maximum_guests = (int)($_POST['maximum_guests'] ?? 1);
            $price_per_day = (float)($_POST['price_per_day'] ?? 0);
            $allowed_types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
            if (!in_array($room_type, $allowed_types, true)) { $room_type = 'other'; }

            $province_id = (int)($_POST['province_id'] ?? 0);
            $district_id = (int)($_POST['district_id'] ?? 0);
            $city_id = (int)($_POST['city_id'] ?? 0);
            $address = trim($_POST['address'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');

            if ($title === '' || $price_per_day < 0 || $beds < 0 || $maximum_guests < 1 || $province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
                $error = 'Please fill all required fields (including location)';
            } elseif (mb_strlen($title) > 150) {
                $error = 'Title is too long';
            } elseif (mb_strlen($postal_code) > 10) {
                $error = 'Postal code is too long';
            } elseif (mb_strlen($address) > 255) {
                $error = 'Address is too long';
            } else {
                $u = db()->prepare('UPDATE rooms SET title=?, room_type=?, description=?, beds=?, maximum_guests=?, price_per_day=?, updated_at=NOW() WHERE room_id=? AND owner_id=?');
                $u->bind_param('sssiidii', $title, $room_type, $description, $beds, $maximum_guests, $price_per_day, $rid, $owner_id);
                $ok = $u->execute();
                $u->close();
                if ($ok) {
                    $locUp = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE room_id=?');
                    $locUp->bind_param('iiiisi', $province_id, $district_id, $city_id, $address, $postal_code, $rid);
                    $locUp->execute();
                    $affected = $locUp->affected_rows;
                    $locUp->close();
                    if ($affected <= 0) {
                        $locIns = db()->prepare('INSERT INTO locations (room_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                        $locIns->bind_param('iiiiss', $rid, $province_id, $district_id, $city_id, $address, $postal_code);
                        $locIns->execute();
                        $locIns->close();
                    }

                    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                        $imgSize = (int)($_FILES['image']['size'] ?? 0);
                        $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
                        if ($imgSize > 0 && $imgSize <= 5242880 && $imgInfo !== false) {
                            $dir = dirname(__DIR__) . '/uploads/rooms';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                            $fname = 'room_' . $rid . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                                $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)');
                                if ($pi) { $pi->bind_param('is', $rid, $rel); $pi->execute(); $pi->close(); }
                            }
                        }
                    }
                    if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                        $count = count($_FILES['gallery_images']['name']);
                        for ($i=0; $i<$count; $i++) {
                            if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                            $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                            $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                            if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                            $dir = dirname(__DIR__) . '/uploads/rooms';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $fname = 'room_' . $rid . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                                $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)');
                                if ($pi) { $pi->bind_param('is', $rid, $rel); $pi->execute(); $pi->close(); }
                            }
                        }
                    }

                    redirect_with_message($GLOBALS['base_url'] . '/owner/room_management.php', 'Room updated', 'success');
                } else {
                    $error = 'Failed to update room';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Room</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Room</h1>
    <a href="room_management.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Update Details</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($room['title'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Room Type</label>
              <select name="room_type" class="form-select">
                <?php
                  $types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
                  $curt = (string)($room['room_type'] ?? 'other');
                  foreach ($types as $t) {
                    $sel = $t===$curt ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($t).'" '.$sel.'>'.ucwords(str_replace('_',' ',$t)).'</option>';
                  }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" rows="3" class="form-control"><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
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
                <input name="postal_code" class="form-control" required maxlength="10" value="<?php echo htmlspecialchars($room['postal_code'] ?? ''); ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input name="address" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($room['address'] ?? ''); ?>">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Price per day (LKR)</label>
                <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required value="<?php echo htmlspecialchars((string)$room['price_per_day']); ?>">
              </div>
              <div class="col">
                <label class="form-label">Beds</label>
                <input name="beds" type="number" min="0" class="form-control" value="<?php echo (int)($room['beds'] ?? 1); ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Maximum Guests</label>
              <input name="maximum_guests" type="number" min="1" class="form-control" required value="<?php echo (int)($room['maximum_guests'] ?? 1); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Primary Image</label>
              <input type="file" name="image" accept="image/*" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Gallery Images</label>
              <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card mb-4">
        <div class="card-header">Current Preview</div>
        <div class="card-body">
          <div class="mb-3">
            <div class="text-muted small">Code</div>
            <div class="fw-semibold"><?php echo 'ROOM-' . str_pad((string)$room['room_id'], 6, '0', STR_PAD_LEFT); ?></div>
          </div>
          <div class="ratio ratio-16x9 mb-3 bg-light rounded d-flex align-items-center justify-content-center">
            <?php
              $img = '';
              try {
                $rs = db()->prepare('SELECT image_path FROM room_images WHERE room_id=? ORDER BY is_primary DESC, image_id DESC LIMIT 1');
                $rs->bind_param('i', $rid);
                $rs->execute();
                $imgr = $rs->get_result()->fetch_assoc();
                $rs->close();
                $img = $imgr['image_path'] ?? '';
              } catch (Throwable $e) {}
            ?>
            <?php if (!empty($img)): ?>
              <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="img-fluid rounded">
            <?php else: ?>
              <span class="text-muted">No image</span>
            <?php endif; ?>
          </div>
          <div class="row g-2 text-muted small">
            <div class="col">Beds: <?php echo (int)($room['beds'] ?? 0); ?></div>
            <div class="col">Guests: <?php echo (int)($room['maximum_guests'] ?? 0); ?></div>
            <div class="col">Type: <?php echo htmlspecialchars($room['room_type'] ?? ''); ?></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">Gallery</div>
        <div class="card-body">
          <div class="row g-3">
            <?php
              $imgs = [];
              try {
                $qi = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, image_id DESC');
                $qi->bind_param('i', $rid);
                $qi->execute();
                $rs = $qi->get_result();
                while ($r = $rs->fetch_assoc()) { $imgs[] = $r; }
                $qi->close();
              } catch (Throwable $e) {}
            ?>
            <?php if ($imgs): ?>
              <?php foreach ($imgs as $im): ?>
                <div class="col-6">
                  <div class="border rounded p-2 h-100 d-flex flex-column">
                    <div class="ratio ratio-4x3 mb-2 bg-light rounded">
                      <img src="<?php echo htmlspecialchars($im['image_path']); ?>" class="img-fluid rounded" alt="">
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="badge <?php echo ((int)$im['is_primary'])? 'bg-primary' : 'bg-secondary'; ?>">
                        <?php echo ((int)$im['is_primary'])? 'Primary' : 'Gallery'; ?>
                      </span>
                      <div class="d-flex gap-2">
                        <?php if (!(int)$im['is_primary']): ?>
                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="set_primary">
                            <input type="hidden" name="image_id" value="<?php echo (int)$im['image_id']; ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Set Primary</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Delete this image?');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="action" value="delete_image">
                          <input type="hidden" name="image_id" value="<?php echo (int)$im['image_id']; ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-12 text-muted">No gallery images.</div>
            <?php endif; ?>
          </div>
          <hr class="my-3">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="upload_images">
            <div class="mb-2">
              <label class="form-label">Add more images</label>
              <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
            </div>
            <button class="btn btn-outline-secondary" type="submit">Upload</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
  function fillSelect(select, items, placeholder, selectedValue) {
    select.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = placeholder;
    ph.disabled = true; ph.selected = !selectedValue;
    select.appendChild(ph);
    (items || []).forEach(item => {
      const value = item.value ?? item.id ?? '';
      const label = item.label ?? item.name ?? String(value);
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      if (String(value) === String(selectedValue)) opt.selected = true;
      select.appendChild(opt);
    });
  }
  document.addEventListener('DOMContentLoaded', () => {
    const provSel = document.getElementById('province');
    const distSel = document.getElementById('district');
    const citySel = document.getElementById('city');
    const baseUrl = 'room_management.php';
    const current = {
      province_id: '<?php echo (int)($room['province_id'] ?? 0); ?>',
      district_id: '<?php echo (int)($room['district_id'] ?? 0); ?>',
      city_id: '<?php echo (int)($room['city_id'] ?? 0); ?>'
    };
    fetch(baseUrl + '?geo=provinces')
      .then(r=>r.json())
      .then(list=>{
        fillSelect(provSel, list.map(x=>({value:x.province_id,label:x.name})), 'Select province', current.province_id);
        if (current.province_id) {
          return fetch(baseUrl + '?geo=districts&province_id=' + encodeURIComponent(current.province_id))
            .then(r=>r.json())
            .then(list=>{
              fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district', current.district_id);
              if (current.district_id) {
                return fetch(baseUrl + '?geo=cities&district_id=' + encodeURIComponent(current.district_id))
                  .then(r=>r.json())
                  .then(list=>fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city', current.city_id));
              }
            });
        }
      })
      .catch(()=>{});
    provSel.addEventListener('change', ()=>{
      const pid = encodeURIComponent(provSel.value||'');
      fetch(baseUrl + '?geo=districts&province_id=' + pid)
        .then(r=>r.json())
        .then(list=>{ fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district'); fillSelect(citySel, [], 'Select city'); });
    });
    distSel.addEventListener('change', ()=>{
      const did = encodeURIComponent(distSel.value||'');
      fetch(baseUrl + '?geo=cities&district_id=' + did)
        .then(r=>r.json())
        .then(list=>fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city'));
    });
  });
</script>
</body>
</html>
