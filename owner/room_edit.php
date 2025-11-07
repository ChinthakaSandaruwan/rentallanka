<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
    redirect_with_message('room_management.php', 'Invalid room ID', 'error');
}

$room = null; $loc = null; $images = [];
try {
    $rs = db()->prepare('SELECT * FROM rooms WHERE room_id=? AND owner_id=? LIMIT 1');
    $rs->bind_param('ii', $rid, $uid);
    $rs->execute();
    $room = $rs->get_result()->fetch_assoc();
    $rs->close();
    if ($room) {
        $ls = db()->prepare('SELECT * FROM locations WHERE room_id=? LIMIT 1');
        $ls->bind_param('i', $rid);
        $ls->execute();
        $loc = $ls->get_result()->fetch_assoc();
        $ls->close();
        // Load images
        $is = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, image_id DESC');
        $is->bind_param('i', $rid);
        $is->execute();
        $rs = $is->get_result();
        while ($row = $rs->fetch_assoc()) { $images[] = $row; }
        $is->close();
    }
} catch (Throwable $e) { }
if (!$room) { redirect_with_message('room_management.php', 'Room not found', 'error'); }
$createdTs = strtotime((string)($room['created_at'] ?? ''));
$editable = $createdTs ? ($createdTs + 24*3600) > time() : false;
if (!$editable) {
    redirect_with_message('room_management.php', 'Editing locked after 24 hours from creation.', 'error');
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }

$alert = ['type'=>'','msg'=>''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $alert = ['type'=>'danger','msg'=>'Invalid request'];
    } else {
        $action = $_POST['action'] ?? 'update';
        if ($action === 'delete_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            if ($imgId > 0) {
                $del = db()->prepare('DELETE FROM room_images WHERE image_id=? AND room_id=?');
                $del->bind_param('ii', $imgId, $rid);
                if ($del->execute() && $del->affected_rows > 0) {
                    $del->close();
                    redirect_with_message('room_edit.php?id=' . $rid, 'Image deleted', 'success');
                }
                if ($del) { $del->close(); }
            }
            $alert = ['type'=>'danger','msg'=>'Failed to delete image'];
        } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $room_type = $_POST['room_type'] ?? 'other';
        $allowed_types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
        if (!in_array($room_type, $allowed_types, true)) { $room_type = 'other'; }
        $beds = (int)($_POST['beds'] ?? 0);
        $maximum_guests = (int)($_POST['maximum_guests'] ?? 1);
        $price_per_day = (float)($_POST['price_per_day'] ?? 0);
        $province_id = (int)($_POST['province_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $city_id = (int)($_POST['city_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        if ($title === '' || $price_per_day < 0 || $maximum_guests < 1) {
            $alert = ['type'=>'danger','msg'=>'Title, price and guests are required'];
        } elseif ($province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
            $alert = ['type'=>'danger','msg'=>'Location (province, district, city, postal code) is required'];
        } elseif (mb_strlen($title) > 150) {
            $alert = ['type'=>'danger','msg'=>'Title is too long'];
        } elseif (mb_strlen($postal_code) > 10) {
            $alert = ['type'=>'danger','msg'=>'Postal code is too long'];
        } elseif (mb_strlen($address) > 255) {
            $alert = ['type'=>'danger','msg'=>'Address is too long'];
        } elseif ($beds < 0) {
            $alert = ['type'=>'danger','msg'=>'Beds must be non-negative'];
        } elseif ($maximum_guests < 1) {
            $alert = ['type'=>'danger','msg'=>'Maximum guests must be at least 1'];
        } elseif ($price_per_day < 0) {
            $alert = ['type'=>'danger','msg'=>'Price per day must be non-negative'];
        } else {
            $up = db()->prepare('UPDATE rooms SET title=?, description=?, room_type=?, beds=?, maximum_guests=?, price_per_day=? WHERE room_id=? AND owner_id=?');
            $up->bind_param('sssiidii', $title, $description, $room_type, $beds, $maximum_guests, $price_per_day, $rid, $uid);
            if (!$up) { $alert = ['type'=>'danger','msg'=>'Failed to prepare']; }
            if (isset($up) && $up && $up->execute()) {
                $up->close();
                try {
                    if ($loc) {
                        $lu = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE room_id=?');
                        $lu->bind_param('iiissi', $province_id, $district_id, $city_id, $address, $postal_code, $rid);
                        $lu->execute();
                        $lu->close();
                    } else {
                        $li = db()->prepare('INSERT INTO locations (room_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                        $li->bind_param('iiiiss', $rid, $province_id, $district_id, $city_id, $address, $postal_code);
                        $li->execute();
                        $li->close();
                    }
                } catch (Throwable $e) { }

                if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $imgSize = (int)($_FILES['image']['size'] ?? 0);
                    $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
                    if ($imgSize <= 0 || $imgSize > 5242880 || $imgInfo === false) {
                        $alert = ['type'=>'danger','msg'=>'Primary image must be a valid image under 5MB'];
                    } else {
                    $dir = dirname(__DIR__) . '/uploads/rooms'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
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
                    $dir = dirname(__DIR__) . '/uploads/rooms'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    for ($i=0; $i<$count; $i++) {
                        if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                        $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                        $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                        if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                        $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                        $fname = 'room_' . $rid . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                        $dest = $dir . '/' . $fname;
                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                            $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                            $gi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)');
                            if ($gi) { $gi->bind_param('is', $rid, $rel); $gi->execute(); $gi->close(); }
                        }
                    }
                }

                redirect_with_message('room_management.php', 'Room updated successfully.', 'success');
            } else if (isset($up)) {
                $alert = ['type'=>'danger','msg'=>'Failed to update'];
                if ($up) { $up->close(); }
            }
        }
    }
}
}

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Room</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Room #<?php echo (int)$rid; ?></h1>
    <a href="room_management.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($flash): ?><div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($alert['msg']): ?><div class="alert alert-<?php echo $alert['type']; ?>"><?php echo htmlspecialchars($alert['msg']); ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="action" value="update">
    <div class="col-12 col-md-6">
      <label class="form-label">Title</label>
      <input name="title" class="form-control" value="<?php echo htmlspecialchars($room['title'] ?? ''); ?>" required maxlength="150">
    </div>
    <div class="col-12">
      <label class="form-label">Description</label>
      <textarea name="description" rows="4" class="form-control"><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Room type</label>
      <select name="room_type" class="form-select">
        <?php
          $types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
          $curT = (string)($room['room_type'] ?? 'other');
          foreach ($types as $t) {
            $sel = $curT === $t ? 'selected' : '';
            echo '<option value="'.htmlspecialchars($t).'" '.$sel.'>'.htmlspecialchars(ucwords(str_replace('_',' ',$t))).'</option>';
          }
        ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Beds</label>
      <input name="beds" type="number" min="0" class="form-control" value="<?php echo (int)($room['beds'] ?? 0); ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Max guests</label>
      <input name="maximum_guests" type="number" min="1" class="form-control" value="<?php echo (int)($room['maximum_guests'] ?? 1); ?>" required>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Price per day (LKR)</label>
      <input name="price_per_day" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars((string)($room['price_per_day'] ?? '0')); ?>" required>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Province</label>
      <input name="province_id" type="number" min="1" class="form-control" value="<?php echo (int)($loc['province_id'] ?? 0); ?>" required>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">District</label>
      <input name="district_id" type="number" min="1" class="form-control" value="<?php echo (int)($loc['district_id'] ?? 0); ?>" required>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">City</label>
      <input name="city_id" type="number" min="1" class="form-control" value="<?php echo (int)($loc['city_id'] ?? 0); ?>" required>
    </div>
    <div class="col-12 col-md-8">
      <label class="form-label">Postal Code</label>
      <input name="postal_code" class="form-control" value="<?php echo htmlspecialchars((string)($loc['postal_code'] ?? '')); ?>" required maxlength="10">
    </div>
    <div class="col-12">
      <label class="form-label">Address</label>
      <input name="address" class="form-control" value="<?php echo htmlspecialchars((string)($loc['address'] ?? '')); ?>" maxlength="255">
    </div>

    <?php if ($images): ?>
    <div class="col-12">
      <label class="form-label d-block">Current Images</label>
      <div class="row g-3">
        <?php foreach ($images as $img): ?>
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="card-img-top" alt="room image">
              <div class="card-body p-2">
                <?php if ((int)($img['is_primary'] ?? 0) === 1): ?>
                  <span class="badge text-bg-primary">Primary</span>
                <?php endif; ?>
              </div>
              <div class="card-footer p-2 text-end">
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this image?');">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="action" value="delete_image">
                  <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-12">
      <label class="form-label">Replace Primary Image (optional)</label>
      <input type="file" name="image" accept="image/*" class="form-control">
    </div>
    <div class="col-12">
      <label class="form-label">Add Gallery Images</label>
      <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
    </div>

    <div class="col-12">
      <button class="btn btn-primary" type="submit">Save Changes</button>
    </div>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
