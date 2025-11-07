<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
    redirect_with_message('property_management.php', 'Invalid property ID', 'error');
}

// Load property owned by user
$prop = null; $loc = null;
try {
    $ps = db()->prepare('SELECT * FROM properties WHERE property_id=? AND owner_id=? LIMIT 1');
    $ps->bind_param('ii', $pid, $uid);
    $ps->execute();
    $prop = $ps->get_result()->fetch_assoc();
    $ps->close();
    if ($prop) {
        $ls = db()->prepare('SELECT * FROM locations WHERE property_id=? LIMIT 1');
        $ls->bind_param('i', $pid);
        $ls->execute();
        $loc = $ls->get_result()->fetch_assoc();
        $ls->close();
    }
} catch (Throwable $e) { /* ignore */ }
if (!$prop) { redirect_with_message('property_management.php', 'Property not found', 'error'); }
// Server-side enforcement: block edits after 24 hours from creation
$createdTs = strtotime((string)($prop['created_at'] ?? ''));
$editable = $createdTs ? ($createdTs + 24*3600) > time() : false;
if (!$editable) {
    redirect_with_message('property_management.php', 'Editing locked after 24 hours from creation.', 'error');
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }

$alert = ['type'=>'','msg'=>''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $alert = ['type'=>'danger','msg'=>'Invalid request'];
    } else {
        // Collect fields
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price_per_month = (float)($_POST['price_per_month'] ?? 0);
        $bedrooms = (int)($_POST['bedrooms'] ?? 0);
        $bathrooms = (int)($_POST['bathrooms'] ?? 0);
        $living_rooms = (int)($_POST['living_rooms'] ?? 0);
        $garden = isset($_POST['garden']) ? 1 : 0;
        $gym = isset($_POST['gym']) ? 1 : 0;
        $pool = isset($_POST['pool']) ? 1 : 0;
        $has_kitchen = isset($_POST['has_kitchen']) ? 1 : 0;
        $has_parking = isset($_POST['has_parking']) ? 1 : 0;
        $has_water_supply = isset($_POST['has_water_supply']) ? 1 : 0;
        $has_electricity_supply = isset($_POST['has_electricity_supply']) ? 1 : 0;
        $sqft = $_POST['sqft'] ?? null; $sqft = ($sqft === '' || $sqft === null) ? null : (float)$sqft;
        $allowed_types = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
        $property_type = $_POST['property_type'] ?? 'other';
        if (!in_array($property_type, $allowed_types, true)) { $property_type = 'other'; }
        $province_id = (int)($_POST['province_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $city_id = (int)($_POST['city_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        if ($title === '' || $price_per_month < 0) {
            $alert = ['type'=>'danger','msg'=>'Title and price are required'];
        } elseif ($province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
            $alert = ['type'=>'danger','msg'=>'Location (province, district, city, postal code) is required'];
        } else {
            // Update properties table (align schema field names)
            $up = db()->prepare('UPDATE properties SET title=?, description=?, price_per_month=?, bedrooms=?, bathrooms=?, living_rooms=?, garden=?, gym=?, pool=?, sqft=?, kitchen=?, parking=?, water_supply=?, electricity_supply=?, property_type=? WHERE property_id=? AND owner_id=?');
            $up->bind_param('ssdi iiii diiii sii', $title, $description, $price_per_month, $bedrooms, $bathrooms, $living_rooms, $garden, $gym, $pool, $sqft, $has_kitchen, $has_parking, $has_water_supply, $has_electricity_supply, $property_type, $pid, $uid);
        }
        if (isset($up) && $up && $up->execute()) {
            $up->close();
            // Upsert location
            try {
                if ($loc) {
                    $lu = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE property_id=?');
                    $lu->bind_param('iii ssi', $province_id, $district_id, $city_id, $address, $postal_code, $pid);
                    $lu->execute();
                    $lu->close();
                } else {
                    $li = db()->prepare('INSERT INTO locations (property_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                    $li->bind_param('iii iss', $pid, $province_id, $district_id, $city_id, $address, $postal_code);
                    $li->execute();
                    $li->close();
                }
            } catch (Throwable $e) { /* ignore */ }

            // Optional: handle new images
            if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $dir = dirname(__DIR__) . '/uploads/properties'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fname = 'prop_' . $pid . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                $dest = $dir . '/' . $fname;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                    $pi = db()->prepare('UPDATE properties SET image=? WHERE property_id=?');
                    $pi->bind_param('si', $rel, $pid);
                    $pi->execute();
                    $pi->close();
                    // also add to property_images as primary if none
                    $hasPrimary = false;
                    $chk = db()->prepare('SELECT COUNT(*) c FROM property_images WHERE property_id=? AND is_primary=1');
                    $chk->bind_param('i', $pid); $chk->execute();
                    $c = $chk->get_result()->fetch_assoc()['c'] ?? 0; $chk->close();
                    if ((int)$c === 0) {
                        $ins = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 1)');
                        $ins->bind_param('is', $pid, $rel); $ins->execute(); $ins->close();
                    }
                }
            }
            if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                $count = count($_FILES['gallery_images']['name']);
                $dir = dirname(__DIR__) . '/uploads/properties'; if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                for ($i=0; $i<$count; $i++) {
                    if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                    $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                    $fname = 'prop_' . $pid . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                    $dest = $dir . '/' . $fname;
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                        $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                        $gi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)');
                        $gi->bind_param('is', $pid, $rel); $gi->execute(); $gi->close();
                    }
                }
            }

            // notify an admin that owner updated the property
            try {
                $adm = 0; $qa = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
                if ($qa && ($row = $qa->fetch_assoc())) { $adm = (int)$row['user_id']; }
                if ($adm > 0) {
                    $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)');
                    $title = 'Property updated by owner';
                    $msg = 'Owner #'.$uid.' updated property #'.$pid;
                    $type = 'system';
                    $nt->bind_param('isssi', $adm, $title, $msg, $type, $pid);
                    $nt->execute();
                    $nt->close();
                }
            } catch (Throwable $e) { }
            redirect_with_message('property_management.php', 'Property updated successfully.', 'success');
        } else if (isset($up)) {
            $alert = ['type'=>'danger','msg'=>'Failed to update'];
            if ($up) { $up->close(); }
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Property #<?php echo (int)$pid; ?></h1>
    <a href="property_management.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($flash): ?><div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($alert['msg']): ?><div class="alert alert-<?php echo $alert['type']; ?>"><?php echo htmlspecialchars($alert['msg']); ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <div class="col-12 col-md-6">
      <label class="form-label">Title</label>
      <input name="title" class="form-control" value="<?php echo htmlspecialchars($prop['title'] ?? ''); ?>" required>
    </div>
    <div class="col-12">
      <label class="form-label">Description</label>
      <textarea name="description" rows="4" class="form-control"><?php echo htmlspecialchars($prop['description'] ?? ''); ?></textarea>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Price per month (LKR)</label>
      <input name="price_per_month" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars((string)($prop['price_per_month'] ?? '0')); ?>" required>
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label">Bedrooms</label>
      <input name="bedrooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['bedrooms'] ?? 0); ?>">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label">Bathrooms</label>
      <input name="bathrooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['bathrooms'] ?? 0); ?>">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label">Living rooms</label>
      <input name="living_rooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['living_rooms'] ?? 0); ?>">
    </div>

    <div class="col-12">
      <label class="form-label">Property type</label>
      <select name="property_type" class="form-select">
        <?php
          $types = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
          $curT = (string)($prop['property_type'] ?? 'other');
          foreach ($types as $t) {
            $sel = $curT === $t ? 'selected' : '';
            echo '<option value="'.htmlspecialchars($t).'" '.$sel.'>'.htmlspecialchars(ucwords(str_replace('_',' ',$t))).'</option>';
          }
        ?>
      </select>
    </div>

    <div class="col-6 col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="has_kitchen" id="has_kitchen" <?php echo ((int)($prop['kitchen'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="has_kitchen">Kitchen</label>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="has_parking" id="has_parking" <?php echo ((int)($prop['parking'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="has_parking">Parking</label>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="has_water_supply" id="has_water_supply" <?php echo ((int)($prop['water_supply'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="has_water_supply">Water</label>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="has_electricity_supply" id="has_electricity_supply" <?php echo ((int)($prop['electricity_supply'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="has_electricity_supply">Electricity</label>
      </div>
    </div>

    <div class="col-4 col-md-2">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="garden" id="garden" <?php echo ((int)($prop['garden'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="garden">Garden</label>
      </div>
    </div>
    <div class="col-4 col-md-2">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="gym" id="gym" <?php echo ((int)($prop['gym'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="gym">Gym</label>
      </div>
    </div>
    <div class="col-4 col-md-2">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="pool" id="pool" <?php echo ((int)($prop['pool'] ?? 0)) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="pool">Pool</label>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="sqft">Area (sqft)</label>
      <input class="form-control" type="number" name="sqft" id="sqft" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($prop['sqft'] ?? '')); ?>">
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
      <input name="postal_code" class="form-control" value="<?php echo htmlspecialchars((string)($loc['postal_code'] ?? '')); ?>" required>
    </div>
    <div class="col-12">
      <label class="form-label">Address</label>
      <input name="address" class="form-control" value="<?php echo htmlspecialchars((string)($loc['address'] ?? '')); ?>">
    </div>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
