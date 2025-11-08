<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Invalid property', 'error');
}

$q = db()->prepare("SELECT p.*, l.province_id, l.district_id, l.city_id, l.address, l.postal_code FROM properties p LEFT JOIN locations l ON l.property_id=p.property_id WHERE p.property_id=? AND p.owner_id=? LIMIT 1");
$q->bind_param('ii', $id, $uid);
$q->execute();
$prop = $q->get_result()->fetch_assoc();
$q->close();
if (!$prop) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property not found', 'error');
}

$created_ts = strtotime((string)($prop['created_at'] ?? ''));
$can_edit = $created_ts ? (($created_ts + 24*3600) > time()) : true;
if (!$can_edit) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Editing locked after 24 hours', 'error');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? 'update';
        if ($action === 'update') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_raw = $_POST['price_per_month'] ?? '';
            $price_per_month = ($price_raw === '' ? null : (float)$price_raw);
            $bedrooms = (int)($_POST['bedrooms'] ?? 0);
            $bathrooms = (int)($_POST['bathrooms'] ?? 0);
            $living_rooms = (int)($_POST['living_rooms'] ?? 0);
            $garden = isset($_POST['garden']) ? 1 : 0;
            $gym = isset($_POST['gym']) ? 1 : 0;
            $pool = isset($_POST['pool']) ? 1 : 0;
            $sqft = $_POST['sqft'] ?? null;
            $sqft = ($sqft === '' || $sqft === null) ? null : (float)$sqft;
            $province_id = (int)($_POST['province_id'] ?? 0);
            $district_id = (int)($_POST['district_id'] ?? 0);
            $city_id = (int)($_POST['city_id'] ?? 0);
            $address = trim($_POST['address'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            $has_kitchen = isset($_POST['has_kitchen']) ? 1 : 0;
            $has_parking = isset($_POST['has_parking']) ? 1 : 0;
            $has_water_supply = isset($_POST['has_water_supply']) ? 1 : 0;
            $has_electricity_supply = isset($_POST['has_electricity_supply']) ? 1 : 0;
            $allowed_types = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
            $property_type = $_POST['property_type'] ?? 'other';
            if (!in_array($property_type, $allowed_types, true)) { $property_type = 'other'; }

            if ($title === '' || $price_raw === '' || (float)$price_per_month <= 0) {
                $error = 'Title and price are required';
            } elseif ($province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
                $error = 'Location (province, district, city, postal code) is required';
            } elseif (mb_strlen($title) > 255) {
                $error = 'Title is too long';
            } elseif (mb_strlen($postal_code) > 10) {
                $error = 'Postal code is too long';
            } elseif (mb_strlen($address) > 255) {
                $error = 'Address is too long';
            } elseif ($bedrooms < 0 || $bathrooms < 0 || $living_rooms < 0) {
                $error = 'Numeric values must be non-negative';
            } elseif (!is_null($sqft) && $sqft < 0) {
                $error = 'Area must be non-negative';
            } else {
                $sql = 'UPDATE properties SET title=?, description=?, price_per_month=?, bedrooms=?, bathrooms=?, living_rooms=?, garden=?, gym=?, pool=?, ';
                $types = 'ssdiiiiii';
                $params = [
                    $title,
                    $description,
                    (float)$price_per_month,
                    $bedrooms,
                    $bathrooms,
                    $living_rooms,
                    $garden,
                    $gym,
                    $pool
                ];
                if (is_null($sqft)) {
                    $sql .= 'sqft=NULL, ';
                } else {
                    $sql .= 'sqft=?, ';
                    $types .= 'd';
                    $params[] = (float)$sqft;
                }
                $sql .= 'kitchen=?, parking=?, water_supply=?, electricity_supply=?, property_type=?, updated_at=NOW() WHERE property_id=? AND owner_id=?';
                $types .= 'iiiisii';
                $params[] = $has_kitchen;
                $params[] = $has_parking;
                $params[] = $has_water_supply;
                $params[] = $has_electricity_supply;
                $params[] = $property_type;
                $params[] = $id;
                $params[] = $uid;

                $u = db()->prepare($sql);
                $u->bind_param($types, ...$params);
                $ok = $u->execute();
                $u->close();
                if ($ok) {
                    $exists = db()->prepare('SELECT 1 FROM locations WHERE property_id=? LIMIT 1');
                    $exists->bind_param('i', $id);
                    $exists->execute();
                    $ex = $exists->get_result()->fetch_row();
                    $exists->close();
                    if ($ex) {
                        $locUp = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE property_id=?');
                        $locUp->bind_param('iiiisi', $province_id, $district_id, $city_id, $address, $postal_code, $id);
                        $locUp->execute();
                        $locUp->close();
                    } else {
                        $locIns = db()->prepare('INSERT INTO locations (property_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                        $locIns->bind_param('iiiiss', $id, $province_id, $district_id, $city_id, $address, $postal_code);
                        $locIns->execute();
                        $locIns->close();
                    }
                    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property updated', 'success');
                } else {
                    $error = 'Failed to update property';
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
  <title>Edit Property</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Property</h1>
    <div class="btn-group">
      <a href="property_management.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <a href="property_image_edit.php?id=<?php echo (int)$id; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-images"></i> Edit Images
      </a>
    </div>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
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
                <input name="title" class="form-control" required maxlength="255" placeholder="Spacious 3BR house..." value="<?php echo htmlspecialchars($prop['title'] ?? ''); ?>">
                <div class="invalid-feedback">Please enter a title (max 255 characters).</div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" rows="4" class="form-control" placeholder="Describe the property, amenities, nearby places..."><?php echo htmlspecialchars($prop['description'] ?? ''); ?></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Province</label>
                <select name="province_id" id="province" class="form-select" required data-current="<?php echo (int)($prop['province_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a province.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">District</label>
                <select name="district_id" id="district" class="form-select" required data-current="<?php echo (int)($prop['district_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a district.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City</label>
                <select name="city_id" id="city" class="form-select" required data-current="<?php echo (int)($prop['city_id'] ?? 0); ?>"></select>
                <div class="invalid-feedback">Please select a city.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Postal Code</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-mailbox"></i></span>
                  <input name="postal_code" class="form-control" required maxlength="10" placeholder="e.g. 10115" value="<?php echo htmlspecialchars($prop['postal_code'] ?? ''); ?>">
                  <div class="invalid-feedback">Please provide a postal code.</div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input name="address" class="form-control" maxlength="255" placeholder="Street, number, etc." value="<?php echo htmlspecialchars($prop['address'] ?? ''); ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Price per month (LKR)</label>
              <div class="input-group">
                <span class="input-group-text">LKR</span>
                <input name="price_per_month" type="number" min="0" step="0.01" class="form-control" required placeholder="0.00" value="<?php echo htmlspecialchars((string)$prop['price_per_month']); ?>">
                <div class="invalid-feedback">Please enter a valid non-negative price.</div>
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Bedrooms</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-door-open"></i></span>
                  <input name="bedrooms" type="number" min="0" class="form-control" placeholder="0" value="<?php echo (int)($prop['bedrooms'] ?? 0); ?>">
                </div>
              </div>
              <div class="col">
                <label class="form-label">Bathrooms</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-droplet"></i></span>
                  <input name="bathrooms" type="number" min="0" class="form-control" placeholder="0" value="<?php echo (int)($prop['bathrooms'] ?? 0); ?>">
                </div>
              </div>
              <div class="col">
                <label class="form-label">Living rooms</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-house"></i></span>
                  <input name="living_rooms" type="number" min="0" class="form-control" placeholder="0" value="<?php echo (int)($prop['living_rooms'] ?? 0); ?>">
                </div>
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_kitchen" id="has_kitchen" <?php echo ((int)($prop['kitchen'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="has_kitchen">Kitchen</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_parking" id="has_parking" <?php echo ((int)($prop['parking'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="has_parking">Parking</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_water_supply" id="has_water_supply" <?php echo ((int)($prop['water_supply'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="has_water_supply">Water</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_electricity_supply" id="has_electricity_supply" <?php echo ((int)($prop['electricity_supply'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="has_electricity_supply">Electricity</label>
                </div>
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="garden" id="garden" <?php echo ((int)($prop['garden'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="garden">Garden</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="gym" id="gym" <?php echo ((int)($prop['gym'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="gym">Gym</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="pool" id="pool" <?php echo ((int)($prop['pool'] ?? 0)) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="pool">Pool</label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Property type</label>
              <select name="property_type" class="form-select">
                <?php
                  $types = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
                  $curt = (string)($prop['property_type'] ?? 'other');
                  foreach ($types as $t) {
                    $sel = $t===$curt ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($t).'" '.$sel.'>'.ucwords(str_replace('_',' ',$t)).'</option>';
                  }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="sqft">Area (sqft)</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-rulers"></i></span>
                <input class="form-control" type="number" name="sqft" id="sqft" step="0.01" min="0" placeholder="e.g. 1200" value="<?php echo htmlspecialchars((string)($prop['sqft'] ?? '')); ?>">
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
<script src="js/property_edit.js" defer></script>
</body>
</html>

