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
        if ($action === 'update') {
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
    <div class="btn-group">
      <a href="room_management.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <a href="room_image_edit.php?id=<?php echo (int)$rid; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-images"></i> Edit Images
      </a>
    </div>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Update Details</div>
        <div class="card-body">
          <form method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                <input name="title" class="form-control" required maxlength="150" placeholder="Cozy double room..." value="<?php echo htmlspecialchars($room['title'] ?? ''); ?>">
                <div class="invalid-feedback">Please enter a title (max 150 characters).</div>
              </div>
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
              <textarea name="description" rows="3" class="form-control" placeholder="Describe the room, amenities, nearby places..."><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Province</label>
                <select name="province_id" id="province" class="form-select" required data-current="<?php echo (int)($room['province_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a province.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">District</label>
                <select name="district_id" id="district" class="form-select" required data-current="<?php echo (int)($room['district_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a district.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City</label>
                <select name="city_id" id="city" class="form-select" required data-current="<?php echo (int)($room['city_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a city.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Postal Code</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-mailbox"></i></span>
                  <input name="postal_code" class="form-control" required maxlength="10" placeholder="e.g. 10115" value="<?php echo htmlspecialchars($room['postal_code'] ?? ''); ?>">
                  <div class="invalid-feedback">Please provide a postal code.</div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input name="address" class="form-control" maxlength="255" placeholder="Street, number, etc." value="<?php echo htmlspecialchars($room['address'] ?? ''); ?>">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Price per day (LKR)</label>
                <div class="input-group">
                  <span class="input-group-text">LKR</span>
                  <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required placeholder="0.00" value="<?php echo htmlspecialchars((string)$room['price_per_day']); ?>">
                  <div class="invalid-feedback">Please enter a valid non-negative price.</div>
                </div>
              </div>
              <div class="col">
                <label class="form-label">Beds</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-moon"></i></span>
                  <input name="beds" type="number" min="0" class="form-control" placeholder="0" value="<?php echo (int)($room['beds'] ?? 1); ?>">
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Maximum Guests</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-people"></i></span>
                <input name="maximum_guests" type="number" min="1" class="form-control" required placeholder="1" value="<?php echo (int)($room['maximum_guests'] ?? 1); ?>">
                <div class="invalid-feedback">Guests must be at least 1.</div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5"></div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="js/room_edit.js" defer></script>
</body>
</html>
