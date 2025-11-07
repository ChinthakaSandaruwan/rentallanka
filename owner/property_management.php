<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

// AJAX: serve geo data from DB (reference tables: provinces, districts, cities)
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? 'create';
        if ($action === 'delete') {
            $pid = (int)($_POST['property_id'] ?? 0);
            if ($pid > 0) {
                $del = db()->prepare('DELETE FROM properties WHERE property_id=? AND owner_id=?');
                $del->bind_param('ii', $pid, $uid);
                if ($del->execute() && $del->affected_rows > 0) {
                    // notify an admin
                    try { $adm = 0; $qa = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1"); if ($qa && ($row = $qa->fetch_assoc())) { $adm = (int)$row['user_id']; }
                        if ($adm > 0) { $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)'); $title='Property deleted by owner'; $msg='Owner #'.$uid.' deleted property #'.$pid; $type='system'; $nt->bind_param('isssi', $adm, $title, $msg, $type, $pid); $nt->execute(); $nt->close(); }
                    } catch (Throwable $e) {}
                    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property deleted', 'success');
                } else {
                    $error = 'Delete failed';
                }
                $del->close();
            } else {
                $error = 'Bad input';
            }
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
        // Location fields (FKs)
        $province_id = (int)($_POST['province_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $city_id = (int)($_POST['city_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $has_kitchen = isset($_POST['has_kitchen']) ? 1 : 0;
        $has_parking = isset($_POST['has_parking']) ? 1 : 0;
        $has_water_supply = isset($_POST['has_water_supply']) ? 1 : 0;
        $has_electricity_supply = isset($_POST['has_electricity_supply']) ? 1 : 0;
        // Align with DB enum values
        $allowed_types = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
        $property_type = $_POST['property_type'] ?? 'other';
        if (!in_array($property_type, $allowed_types, true)) {
            $property_type = 'other';
        }

        if ($title === '' || $price_per_month < 0) {
            $error = 'Title and price are required';
        } elseif ($province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
            $error = 'Location (province, district, city, postal code) is required';
        } else {
            // Enforce active and paid bought package for properties
            $bp = null; $bp_id = 0; $rem_props = 0;
            try {
                $q = db()->prepare("SELECT bought_package_id, remaining_properties, end_date FROM bought_packages WHERE user_id=? AND status='active' AND payment_status='paid' AND (end_date IS NULL OR end_date>=NOW()) ORDER BY start_date DESC LIMIT 1");
                $q->bind_param('i', $uid);
                $q->execute();
                $bp = $q->get_result()->fetch_assoc();
                $q->close();
                if ($bp) { $bp_id = (int)$bp['bought_package_id']; $rem_props = (int)$bp['remaining_properties']; }
            } catch (Throwable $e) { /* ignore */ }
            if ($bp_id <= 0) {
                redirect_with_message($GLOBALS['base_url'] . '/owner/buy_advertising_packages.php', 'Please buy a property package and complete payment before adding a property.', 'error');
            }
            if ($rem_props <= 0) {
                redirect_with_message($GLOBALS['base_url'] . '/owner/buy_advertising_packages.php', 'Your package does not have remaining property slots.', 'error');
            }
            $stmt = db()->prepare('INSERT INTO properties (owner_id, title, description, price_per_month, bedrooms, bathrooms, living_rooms, garden, gym, pool, sqft, kitchen, parking, water_supply, electricity_supply, property_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param(
                'issdiiiiiidiiiis',
                $uid,
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
                $property_type
            );
            if ($stmt->execute()) {
                $new_id = db()->insert_id;
                $stmt->close();

                // Optional: property_code column may not exist; skip updating it and compute on the fly when displaying

                // Insert location linked by FK IDs
                $loc = db()->prepare('INSERT INTO locations (property_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                $loc->bind_param('iiiiss', $new_id, $province_id, $district_id, $city_id, $address, $postal_code);
                $loc->execute();
                $loc->close();

                // Handle primary image upload
                if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $dir = dirname(__DIR__) . '/uploads/properties';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fname = 'prop_' . $new_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                    $dest = $dir . '/' . $fname;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                        $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?');
                        $up->bind_param('si', $rel, $new_id);
                        $up->execute();
                        $up->close();
                        // Insert into property_images as primary
                        $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 1)');
                        $pi->bind_param('is', $new_id, $rel);
                        $pi->execute();
                        $pi->close();
                    }
                }

                // Handle gallery images (non-primary)
                if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
                    $count = count($_FILES['gallery_images']['name']);
                    for ($i=0; $i < $count; $i++) {
                        if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
                        $dir = dirname(__DIR__) . '/uploads/properties';
                        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                        $ext = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                        $fname = 'prop_' . $new_id . '_' . ($i+1) . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
                        $dest = $dir . '/' . $fname;
                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                            $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                            $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)');
                            $pi->bind_param('is', $new_id, $rel);
                            $pi->execute();
                            $pi->close();
                        }
                    }
                }

                // Payment slip upload not used

                // notify an admin of new submission
                try { $adm = 0; $qa = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1"); if ($qa && ($row = $qa->fetch_assoc())) { $adm = (int)$row['user_id']; }
                    if ($adm > 0) { $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?,?)'); $title='New property submitted'; $msg='Owner #'.$uid.' submitted property #'.$new_id.' for approval'; $type='system'; $nt->bind_param('isssi', $adm, $title, $msg, $type, $new_id); $nt->execute(); $nt->close(); }
                } catch (Throwable $e) {}
                redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property submitted. Awaiting admin approval.', 'success');
            } else {
                $error = 'Failed to create property';
                $stmt->close();
            }
        }
        }
    }
}

$props = [];
$stmt = db()->prepare('SELECT property_id, title, status, created_at, price_per_month FROM properties WHERE owner_id = ? ORDER BY property_id DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $props[] = $row;
}
$stmt->close();

// Fetch current active & paid package info to show remaining property slots
$pkg_info = null;
try {
    $q = db()->prepare("SELECT bp.remaining_properties, bp.end_date, bp.status, bp.payment_status, p.package_name FROM bought_packages bp JOIN packages p ON p.package_id=bp.package_id WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid' ORDER BY bp.start_date DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute();
    $pkg_info = $q->get_result()->fetch_assoc();
    $q->close();
} catch (Throwable $e) { /* ignore */ }

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Property Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Property Management</h1>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
  <?php if ($pkg_info): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
      <i class="bi bi-info-circle me-2"></i>
      <div>
        <strong>Active Package:</strong> <?php echo htmlspecialchars($pkg_info['package_name'] ?? ''); ?>
        | <strong>Remaining Property Slots:</strong> <?php echo (int)($pkg_info['remaining_properties'] ?? 0); ?>
        <?php if (!empty($pkg_info['end_date'])): ?>
          | <strong>Ends:</strong> <?php echo htmlspecialchars($pkg_info['end_date']); ?>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <div>You don't have an active paid package with property slots. Please buy or pay for a package before posting.</div>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <div class="row g-4">
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header">Add Property</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required>
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
                <label class="form-label">Address</label>
                <input name="address" class="form-control">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Price per month (LKR)</label>
              <input name="price_per_month" type="number" min="0" step="0.01" class="form-control" required>
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Bedrooms</label>
                <input name="bedrooms" type="number" min="0" value="0" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Bathrooms</label>
                <input name="bathrooms" type="number" min="0" value="0" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Living rooms</label>
                <input name="living_rooms" type="number" min="0" value="0" class="form-control">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_kitchen" id="has_kitchen">
                  <label class="form-check-label" for="has_kitchen">Kitchen</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_parking" id="has_parking">
                  <label class="form-check-label" for="has_parking">Parking</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_water_supply" id="has_water_supply">
                  <label class="form-check-label" for="has_water_supply">Water</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_electricity_supply" id="has_electricity_supply">
                  <label class="form-check-label" for="has_electricity_supply">Electricity</label>
                </div>
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="garden" id="garden">
                  <label class="form-check-label" for="garden">Garden</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="gym" id="gym">
                  <label class="form-check-label" for="gym">Gym</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="pool" id="pool">
                  <label class="form-check-label" for="pool">Pool</label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Property type</label>
              <select name="property_type" class="form-select">
                <option value="apartment">Apartment</option>
                <option value="house">House</option>
                <option value="villa">Villa</option>
                <option value="duplex">Duplex</option>
                <option value="studio">Studio</option>
                <option value="penthouse">Penthouse</option>
                <option value="bungalow">Bungalow</option>
                <option value="townhouse">Townhouse</option>
                <option value="farmhouse">Farmhouse</option>
                <option value="office">Office</option>
                <option value="shop">Shop</option>
                <option value="warehouse">Warehouse</option>
                <option value="land">Land</option>
                <option value="commercial_building">Commercial Building</option>
                <option value="industrial">Industrial</option>
                <option value="hotel">Hotel</option>
                <option value="guesthouse">Guesthouse</option>
                <option value="resort">Resort</option>
                <option value="other" selected>Other</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="sqft">Area (sqft)</label>
              <input class="form-control" type="number" name="sqft" id="sqft" step="0.01" min="0" placeholder="e.g. 1200">
            </div>
            <div class="mb-3">
              <label class="form-label">Primary Image</label>
              <input type="file" name="image" accept="image/*" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Gallery Images</label>
              <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit for Approval</button>
            <p class="text-muted mt-2 mb-0"><small>Status will be pending until admin approves.</small></p>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Your Properties</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Code</th>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Status</th>
                  <th>Price/mo</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($props as $p): ?>
                  <tr>
                    <td><?php echo 'PROP-' . str_pad((string)$p['property_id'], 6, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo (int)$p['property_id']; ?></td>
                    <td><?php echo htmlspecialchars($p['title']); ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span></td>
                    <td><?php echo number_format((float)$p['price_per_month'], 2); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                    <td class="text-nowrap">
                      <?php $can_edit = (strtotime((string)$p['created_at']) + 24*3600) > time(); ?>
                      <?php if ($can_edit): ?>
                        <a class="btn btn-sm btn-outline-primary me-1" href="property_edit.php?id=<?php echo (int)$p['property_id']; ?>">Edit</a>
                      <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary me-1" type="button" disabled title="Editing locked after 24 hours">Edit</button>
                      <?php endif; ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this property?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$props): ?>
                  <tr><td colspan="6" class="text-center py-4">No properties yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script><script>
  function fillSelect(select, items, placeholder) {
    select.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = placeholder;
    ph.disabled = true; ph.selected = true;
    select.appendChild(ph);
    (items || []).forEach(item => {
      const isObj = typeof item === 'object' && item !== null;
      const value = isObj ? (item.value ?? item.id ?? '') : item;
      const label = isObj ? (item.label ?? item.name ?? String(value)) : String(item);
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      select.appendChild(opt);
    });
    select.disabled = false;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const provSel = document.getElementById('province');
    const distSel = document.getElementById('district');
    const citySel = document.getElementById('city');
    const baseUrl = window.location.pathname;

    // provinces returns [{province_id,name}]
    fetch(baseUrl + '?geo=provinces')
      .then(r => r.json())
      .then(list => fillSelect(provSel, list.map(x=>({value:x.province_id,label:x.name})), 'Select province'))
      .catch(() => fillSelect(provSel, [], 'Select province'));

    provSel.addEventListener('change', () => {
      const pid = encodeURIComponent(provSel.value || '');
      fetch(baseUrl + '?geo=districts&province_id=' + pid)
        .then(r => r.json())
        .then(list => {
          fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district');
          fillSelect(citySel, [], 'Select city');
        })
        .catch(() => {
          fillSelect(distSel, [], 'Select district');
          fillSelect(citySel, [], 'Select city');
        });
    });

    distSel.addEventListener('change', () => {
      const did = encodeURIComponent(distSel.value || '');
      fetch(baseUrl + '?geo=cities&district_id=' + did)
        .then(r => r.json())
        .then(list => fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city'))
        .catch(() => fillSelect(citySel, [], 'Select city'));
    });
  });

  
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKvVYl0ZlEFp3rG5GkHA7r4XK6tBT3M" crossorigin="anonymous"></script>

</body>
</html>
