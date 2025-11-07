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

$can_edit = (strtotime((string)$prop['created_at']) + 24*3600) > time();
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
        if ($action === 'upload_images') {
            if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                $count = count($_FILES['gallery_images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                    $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                    $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                    if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                    $dir = dirname(__DIR__) . '/uploads/properties';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                    $fname = 'prop_' . $id . '_' . ($i + 1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    $dest = $dir . '/' . $fname;
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                        $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                        $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)');
                        if ($pi) { $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); }
                    }
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_edit.php?id=' . (int)$id, 'Images uploaded', 'success');
        } elseif ($action === 'set_primary') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            if ($image_id > 0) {
                $chk = db()->prepare('SELECT image_path FROM property_images WHERE image_id=? AND property_id=?');
                $chk->bind_param('ii', $image_id, $id);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row) {
                    $clr = db()->prepare('UPDATE property_images SET is_primary=0 WHERE property_id=?');
                    $clr->bind_param('i', $id);
                    $clr->execute();
                    $clr->close();
                    $sp = db()->prepare('UPDATE property_images SET is_primary=1 WHERE image_id=? AND property_id=?');
                    $sp->bind_param('ii', $image_id, $id);
                    $sp->execute();
                    $sp->close();
                    $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?');
                    $up->bind_param('si', $row['image_path'], $id);
                    $up->execute();
                    $up->close();
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_edit.php?id=' . (int)$id, 'Primary image updated', 'success');
        } elseif ($action === 'delete_image') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            if ($image_id > 0) {
                $chk = db()->prepare('SELECT image_path, is_primary FROM property_images WHERE image_id=? AND property_id=?');
                $chk->bind_param('ii', $image_id, $id);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row) {
                    $del = db()->prepare('DELETE FROM property_images WHERE image_id=? AND property_id=?');
                    $del->bind_param('ii', $image_id, $id);
                    $del->execute();
                    $del->close();
                    if ((int)$row['is_primary'] === 1) {
                        $nx = db()->prepare('SELECT image_path FROM property_images WHERE property_id=? ORDER BY is_primary DESC, image_id DESC LIMIT 1');
                        $nx->bind_param('i', $id);
                        $nx->execute();
                        $nrow = $nx->get_result()->fetch_assoc();
                        $nx->close();
                        $newPath = $nrow['image_path'] ?? null;
                        if ($newPath) {
                            $clr = db()->prepare('UPDATE property_images SET is_primary=0 WHERE property_id=?');
                            $clr->bind_param('i', $id);
                            $clr->execute();
                            $clr->close();
                            $sp = db()->prepare('UPDATE property_images SET is_primary=1 WHERE property_id=? AND image_path=? LIMIT 1');
                            $sp->bind_param('is', $id, $newPath);
                            $sp->execute();
                            $sp->close();
                            $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?');
                            $up->bind_param('si', $newPath, $id);
                            $up->execute();
                            $up->close();
                        } else {
                            $up = db()->prepare('UPDATE properties SET image=NULL WHERE property_id=?');
                            $up->bind_param('i', $id);
                            $up->execute();
                            $up->close();
                        }
                    }
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_edit.php?id=' . (int)$id, 'Image deleted', 'success');
        } else {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price_per_month = (float)($_POST['price_per_month'] ?? 0);
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

            if ($title === '' || $price_per_month < 0) {
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
                $u = db()->prepare('UPDATE properties SET title=?, description=?, price_per_month=?, bedrooms=?, bathrooms=?, living_rooms=?, garden=?, gym=?, pool=?, sqft=?, kitchen=?, parking=?, water_supply=?, electricity_supply=?, property_type=?, updated_at=NOW() WHERE property_id=? AND owner_id=?');
                $u->bind_param(
                    'ssdiiiiiidiiiisii',
                    $title,
                    $description,
                    $price_per_month,
                    $bedrooms,
                    $bathrooms,
                    $living_rooms,
                    $garden,
                    $gym,
                    $pool,
                    $sqft,
                    $has_kitchen,
                    $has_parking,
                    $has_water_supply,
                    $has_electricity_supply,
                    $property_type,
                    $id,
                    $uid
                );
                $ok = $u->execute();
                $u->close();
                if ($ok) {
                    $locUp = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE property_id=?');
                    $locUp->bind_param('iiiisi', $province_id, $district_id, $city_id, $address, $postal_code, $id);
                    $locUp->execute();
                    $affected = $locUp->affected_rows;
                    $locUp->close();
                    if ($affected <= 0) {
                        $locIns = db()->prepare('INSERT INTO locations (property_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                        $locIns->bind_param('iiiiss', $id, $province_id, $district_id, $city_id, $address, $postal_code);
                        $locIns->execute();
                        $locIns->close();
                    }

                    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                        $imgSize = (int)($_FILES['image']['size'] ?? 0);
                        $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
                        if ($imgSize > 0 && $imgSize <= 5242880 && $imgInfo !== false) {
                            $dir = dirname(__DIR__) . '/uploads/properties';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                            $fname = 'prop_' . $id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                                $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?');
                                $up->bind_param('si', $rel, $id);
                                $up->execute();
                                $up->close();
                                $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 1)');
                                if ($pi) { $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); }
                            }
                        }
                    }

                    if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                        $count = count($_FILES['gallery_images']['name']);
                        for ($i = 0; $i < $count; $i++) {
                            if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                            $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                            $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                            if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                            $dir = dirname(__DIR__) . '/uploads/properties';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $fname = 'prop_' . $id . '_' . ($i + 1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                                $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)');
                                if ($pi) { $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); }
                            }
                        }
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
    <a href="property_management.php" class="btn btn-outline-secondary btn-sm">Back</a>
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
          <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($prop['title'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" rows="4" class="form-control"><?php echo htmlspecialchars($prop['description'] ?? ''); ?></textarea>
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
                <input name="postal_code" class="form-control" required maxlength="10" value="<?php echo htmlspecialchars($prop['postal_code'] ?? ''); ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input name="address" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($prop['address'] ?? ''); ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Price per month (LKR)</label>
              <input name="price_per_month" type="number" min="0" step="0.01" class="form-control" required value="<?php echo htmlspecialchars((string)$prop['price_per_month']); ?>">
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Bedrooms</label>
                <input name="bedrooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['bedrooms'] ?? 0); ?>">
              </div>
              <div class="col">
                <label class="form-label">Bathrooms</label>
                <input name="bathrooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['bathrooms'] ?? 0); ?>">
              </div>
              <div class="col">
                <label class="form-label">Living rooms</label>
                <input name="living_rooms" type="number" min="0" class="form-control" value="<?php echo (int)($prop['living_rooms'] ?? 0); ?>">
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
              <input class="form-control" type="number" name="sqft" id="sqft" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($prop['sqft'] ?? '')); ?>">
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
            <div class="fw-semibold"><?php echo 'PROP-' . str_pad((string)$prop['property_id'], 6, '0', STR_PAD_LEFT); ?></div>
          </div>
          <div class="ratio ratio-16x9 mb-3 bg-light rounded d-flex align-items-center justify-content-center">
            <?php if (!empty($prop['image'])): ?>
              <img src="<?php echo htmlspecialchars($prop['image']); ?>" alt="" class="img-fluid rounded">
            <?php else: ?>
              <span class="text-muted">No primary image</span>
            <?php endif; ?>
          </div>
          <div class="row g-2 text-muted small">
            <div class="col">Bedrooms: <?php echo (int)($prop['bedrooms'] ?? 0); ?></div>
            <div class="col">Bathrooms: <?php echo (int)($prop['bathrooms'] ?? 0); ?></div>
            <div class="col">Living: <?php echo (int)($prop['living_rooms'] ?? 0); ?></div>
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
                $qi = db()->prepare('SELECT image_id, image_path, is_primary FROM property_images WHERE property_id=? ORDER BY is_primary DESC, image_id DESC');
                $qi->bind_param('i', $id);
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
    select.disabled = false;
  }
  document.addEventListener('DOMContentLoaded', () => {
    const provSel = document.getElementById('province');
    const distSel = document.getElementById('district');
    const citySel = document.getElementById('city');
    const baseUrl = 'property_management.php';
    const current = {
      province_id: '<?php echo (int)($prop['province_id'] ?? 0); ?>',
      district_id: '<?php echo (int)($prop['district_id'] ?? 0); ?>',
      city_id: '<?php echo (int)($prop['city_id'] ?? 0); ?>'
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

