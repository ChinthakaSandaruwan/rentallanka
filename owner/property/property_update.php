<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

// Serve geo data for selects (same as create)
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

$id = (int)($_GET['id'] ?? 0);
$selection_mode = false;
$prop = null;
if ($id > 0) {
    $q = db()->prepare("SELECT p.*, l.province_id, l.district_id, l.city_id, l.address, l.google_map_link, l.postal_code FROM properties p LEFT JOIN property_locations l ON l.property_id=p.property_id WHERE p.property_id=? AND p.owner_id=? LIMIT 1");
    $q->bind_param('ii', $id, $uid);
    $q->execute();
    $prop = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$prop) {
        $selection_mode = true;
        $error = 'Property not found';
    } else {
        $created_ts = strtotime((string)($prop['created_at'] ?? ''));
        $can_edit = $created_ts ? (($created_ts + 24*3600) > time()) : true;
        if (!$can_edit) {
            $selection_mode = true;
            $error = 'Editing locked after 24 hours';
        }
    }
} else {
    $selection_mode = true;
}

// Load owner properties for selection if needed
$myprops = [];
if ($selection_mode) {
    try {
        $s = db()->prepare("SELECT property_id, title, property_code, image, created_at, price_per_month FROM properties WHERE owner_id=? ORDER BY created_at DESC LIMIT 100");
        $s->bind_param('i', $uid);
        $s->execute();
        $rs = $s->get_result();
        while ($row = $rs->fetch_assoc()) { $myprops[] = $row; }
        $s->close();
    } catch (Throwable $e) { /* ignore */ }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
$success = '';
if (!$selection_mode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? 'update';
        if ($action === 'img_set_primary') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            // verify image belongs to this property
            $chk = db()->prepare('SELECT image_id, image_path FROM property_images WHERE image_id=? AND property_id=?');
            $chk->bind_param('ii', $image_id, $id);
            $chk->execute();
            $img = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($img) {
                // set is_primary flags and update properties.image
                try { $np = db()->prepare('UPDATE property_images SET is_primary=0 WHERE property_id=?'); $np->bind_param('i', $id); $np->execute(); $np->close(); } catch (Throwable $e) {}
                try { $sp = db()->prepare('UPDATE property_images SET is_primary=1 WHERE image_id=?'); $sp->bind_param('i', $image_id); $sp->execute(); $sp->close(); } catch (Throwable $e) {}
                try { $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?'); $up->bind_param('si', $img['image_path'], $id); $up->execute(); $up->close(); } catch (Throwable $e) {}
                $flash = 'Primary image updated.'; $flash_type = 'success';
            } else {
                $error = 'Image not found';
            }
        } elseif ($action === 'img_delete') {
            $image_id = (int)($_POST['image_id'] ?? 0);
            // fetch image
            $ci = db()->prepare('SELECT image_id, image_path, is_primary FROM property_images WHERE image_id=? AND property_id=?');
            $ci->bind_param('ii', $image_id, $id);
            $ci->execute();
            $img = $ci->get_result()->fetch_assoc();
            $ci->close();
            if ($img) {
                // delete file safely
                $baseDir = realpath(dirname(__DIR__, 2) . '/uploads/properties') ?: '';
                $fname = basename(parse_url($img['image_path'], PHP_URL_PATH) ?? '');
                if ($fname) {
                    $full = dirname(__DIR__, 2) . '/uploads/properties/' . $fname;
                    $real = realpath($full) ?: '';
                    if ($real && $baseDir && strpos($real, $baseDir) === 0 && is_file($real)) { @unlink($real); }
                }
                // delete row
                $dp = db()->prepare('DELETE FROM property_images WHERE image_id=? AND property_id=?');
                $dp->bind_param('ii', $image_id, $id);
                $dp->execute();
                $dp->close();
                // if primary deleted, set another as primary or clear properties.image
                if ((int)$img['is_primary'] === 1) {
                    $nx = db()->prepare('SELECT image_id, image_path FROM property_images WHERE property_id=? ORDER BY uploaded_at DESC LIMIT 1');
                    $nx->bind_param('i', $id);
                    $nx->execute();
                    $next = $nx->get_result()->fetch_assoc();
                    $nx->close();
                    if ($next) {
                        try { $np = db()->prepare('UPDATE property_images SET is_primary=0 WHERE property_id=?'); $np->bind_param('i', $id); $np->execute(); $np->close(); } catch (Throwable $e) {}
                        try { $sp = db()->prepare('UPDATE property_images SET is_primary=1 WHERE image_id=?'); $sp->bind_param('i', $next['image_id']); $sp->execute(); $sp->close(); } catch (Throwable $e) {}
                        try { $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?'); $up->bind_param('si', $next['image_path'], $id); $up->execute(); $up->close(); } catch (Throwable $e) {}
                    } else {
                        try { $up = db()->prepare('UPDATE properties SET image=NULL WHERE property_id=?'); $up->bind_param('i', $id); $up->execute(); $up->close(); } catch (Throwable $e) {}
                    }
                }
                $flash = 'Image deleted.'; $flash_type = 'success';
            } else {
                $error = 'Image not found';
            }
        } elseif ($action === 'update') {
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
            $google_map_link = trim($_POST['google_map_link'] ?? '');
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
            } elseif (mb_strlen($google_map_link) > 255) {
                $error = 'Google map link is too long';
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
                    $exists = db()->prepare('SELECT 1 FROM property_locations WHERE property_id=? LIMIT 1');
                    $exists->bind_param('i', $id);
                    $exists->execute();
                    $ex = $exists->get_result()->fetch_row();
                    $exists->close();
                    if ($ex) {
                        $locUp = db()->prepare('UPDATE property_locations SET province_id=?, district_id=?, city_id=?, address=?, google_map_link=?, postal_code=? WHERE property_id=?');
                        $gmap = ($google_map_link === '' ? null : $google_map_link);
                        $locUp->bind_param('iiisssi', $province_id, $district_id, $city_id, $address, $gmap, $postal_code, $id);
                        $locUp->execute();
                        $locUp->close();
                    } else {
                        $locIns = db()->prepare('INSERT INTO property_locations (property_id, province_id, district_id, city_id, address, google_map_link, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $gmap = ($google_map_link === '' ? null : $google_map_link);
                        $locIns->bind_param('iiiisss', $id, $province_id, $district_id, $city_id, $address, $gmap, $postal_code);
                        $locIns->execute();
                        $locIns->close();
                    }
                    // Optional image updates in the same request
                    $uploaded_primary = false;
                    $uploaded_gallery_count = 0;
                    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                        $imgSize = (int)($_FILES['image']['size'] ?? 0);
                        $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
                        if ($imgSize > 0 && $imgSize <= 5242880 && $imgInfo !== false) {
                            $dir = dirname(__DIR__, 2) . '/uploads/properties';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                            $ext = preg_replace('/[^a-zA-Z0-9]/','', $ext);
                            if ($ext === '' && is_array($imgInfo)) {
                                $mime = $imgInfo['mime'] ?? '';
                                if (strpos($mime, 'jpeg') !== false) $ext = 'jpg';
                                elseif (strpos($mime, 'png') !== false) $ext = 'png';
                                elseif (strpos($mime, 'gif') !== false) $ext = 'gif';
                                elseif (strpos($mime, 'webp') !== false) $ext = 'webp';
                            }
                            if ($ext === '') $ext = 'jpg';
                            $fname = 'prop_' . $id . '_' . time() . '.' . $ext;
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                                try { $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?'); $up->bind_param('si', $rel, $id); $up->execute(); $up->close(); } catch (Throwable $e) {}
                                try { $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 1)'); $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
                                $uploaded_primary = true;
                            }
                        }
                    }
                    if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                        $count = count($_FILES['gallery_images']['name']);
                        for ($i=0; $i < $count; $i++) {
                            if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                            $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
                            $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
                            if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
                            $dir = dirname(__DIR__, 2) . '/uploads/properties';
                            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                            $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $ext = preg_replace('/[^a-zA-Z0-9]/','', $ext);
                            if ($ext === '' && is_array($gInfo)) {
                                $mime = $gInfo['mime'] ?? '';
                                if (strpos($mime, 'jpeg') !== false) $ext = 'jpg';
                                elseif (strpos($mime, 'png') !== false) $ext = 'png';
                                elseif (strpos($mime, 'gif') !== false) $ext = 'gif';
                                elseif (strpos($mime, 'webp') !== false) $ext = 'webp';
                            }
                            if ($ext === '') $ext = 'jpg';
                            $fname = 'prop_' . $id . '_' . ($i+1) . '_' . time() . '.' . $ext;
                            $dest = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                                $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                                try { $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)'); $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
                                $uploaded_gallery_count++;
                            }
                        }
                    }

                    // Keep values sticky on the page and show success
                    $prop['title'] = $title;
                    $prop['description'] = $description;
                    $prop['price_per_month'] = $price_per_month;
                    $prop['bedrooms'] = $bedrooms;
                    $prop['bathrooms'] = $bathrooms;
                    $prop['living_rooms'] = $living_rooms;
                    $prop['garden'] = $garden;
                    $prop['gym'] = $gym;
                    $prop['pool'] = $pool;
                    $prop['kitchen'] = $has_kitchen;
                    $prop['parking'] = $has_parking;
                    $prop['water_supply'] = $has_water_supply;
                    $prop['electricity_supply'] = $has_electricity_supply;
                    $prop['sqft'] = $sqft;
                    $prop['property_type'] = $property_type;
                    $prop['province_id'] = $province_id;
                    $prop['district_id'] = $district_id;
                    $prop['city_id'] = $city_id;
                    $prop['address'] = $address;
                    $prop['google_map_link'] = $google_map_link;
                    $prop['postal_code'] = $postal_code;

                    $flash = 'Property updated successfully.';
                    if ($uploaded_primary || $uploaded_gallery_count > 0) {
                        $flash .= ' Uploaded images: ' . ($uploaded_primary ? 'primary' : 'no primary') . ', gallery ' . $uploaded_gallery_count . '.';
                    }
                    $flash_type = 'success';
                } else {
                    $error = 'Failed to update property';
                }
            }
        }
    }
}

if (empty($flash)) { [$flash, $flash_type] = get_flash(); }
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
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Property</h1>
    <div class="btn-group">
      <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Dashboard
      </a>
    </div>
  </div>
  <?php
    if (!empty($error)) {
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
        . htmlspecialchars($error)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    if (!empty($flash)) {
      $map = ['error' => 'danger', 'danger' => 'danger', 'success' => 'success', 'warning' => 'warning', 'info' => 'info'];
      $type = $map[$flash_type ?? 'info'] ?? 'info';
      echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
        . htmlspecialchars($flash)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
  ?>

  <?php if ($selection_mode): ?>
    <div class="card">
      <div class="card-header">Select a Property to Edit</div>
      <div class="card-body">
        <?php if (empty($myprops)): ?>
          <div class="text-muted">No properties found. Please create a property first.</div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($myprops as $p): ?>
              <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                  <?php $img = trim((string)($p['image'] ?? '')); ?>
                  <?php if ($img): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="Property image">
                  <?php endif; ?>
                  <div class="card-body d-flex flex-column">
                    <div class="text-muted small">Code</div>
                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars($p['property_code'] ?? ('PROP-' . str_pad((string)$p['property_id'], 6, '0', STR_PAD_LEFT))); ?></div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($p['title'] ?? ''); ?></h6>
                    <div class="text-muted small mb-3">Created: <?php echo htmlspecialchars($p['created_at'] ?? ''); ?></div>
                    <?php if (isset($p['price_per_month'])): ?>
                      <div class="h6 mb-3">LKR <?php echo number_format((float)$p['price_per_month'], 2); ?>/mo</div>
                    <?php endif; ?>
                    <a class="btn btn-primary mt-auto w-100" href="property_update.php?id=<?php echo (int)$p['property_id']; ?>">Edit</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">Update Details</div>
          <div class="card-body">
            <div id="formAlert"></div>
            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="update">
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
                <div class="col-12">
                  <label class="form-label">Google Map Link (optional)</label>
                  <input name="google_map_link" class="form-control" maxlength="255" placeholder="https://maps.google.com/..." value="<?php echo htmlspecialchars($prop['google_map_link'] ?? ''); ?>">
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
              <div class="mb-3">
                <label class="form-label">Replace Primary Image (optional, max 5MB)</label>
                <input type="file" name="image" accept="image/*" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Add Gallery Images (optional, max 5MB each)</label>
                <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
              </div>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="card h-100">
          <div class="card-header">Images</div>
          <div class="card-body">
            <?php
              // load existing images
              $imgs = [];
              try {
                $gi = db()->prepare('SELECT image_id, image_path, is_primary, uploaded_at FROM property_images WHERE property_id=? ORDER BY is_primary DESC, uploaded_at DESC');
                $gi->bind_param('i', $id);
                $gi->execute();
                $gr = $gi->get_result();
                while ($r = $gr->fetch_assoc()) { $imgs[] = $r; }
                $gi->close();
              } catch (Throwable $e) {}
            ?>
            <?php if (!$imgs): ?>
              <div class="text-muted">No images uploaded.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach ($imgs as $im): ?>
                  <div class="col-6">
                    <div class="border rounded p-2 h-100 d-flex flex-column">
                      <img src="<?php echo htmlspecialchars($im['image_path']); ?>" class="img-fluid rounded mb-2" alt="Property image">
                      <?php if ((int)$im['is_primary'] === 1): ?>
                        <span class="badge bg-success mb-2">Primary</span>
                      <?php else: ?>
                        <form method="post" class="mb-2">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="action" value="img_set_primary">
                          <input type="hidden" name="image_id" value="<?php echo (int)$im['image_id']; ?>">
                          <button class="btn btn-outline-primary btn-sm w-100" type="submit">Set as Primary</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('Delete this image?');" class="mt-auto">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="img_delete">
                        <input type="hidden" name="image_id" value="<?php echo (int)$im['image_id']; ?>">
                        <button class="btn btn-outline-danger btn-sm w-100" type="submit">Delete</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="js/property_update.js" defer></script>
</body>
</html>

