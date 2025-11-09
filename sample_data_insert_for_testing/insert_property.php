<?php
require_once __DIR__ . '/../config/config.php';

function pick_owner(?int $owner_id): ?int {
  if ($owner_id && $owner_id > 0) return $owner_id;
  $id = null;
  try {
    $res = db()->query("SELECT user_id FROM users WHERE role='owner' ORDER BY RAND() LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) $id = (int)$row['user_id'];
  } catch (Throwable $e) {}
  return $id;
}

function pick_location(): array {
  $province = null; $district = null; $city = null;
  try {
    $rp = db()->query("SELECT id FROM provinces ORDER BY RAND() LIMIT 1");
    $prow = $rp ? $rp->fetch_assoc() : null;
    if ($prow) {
      $province = (int)$prow['id'];
      $rd = db()->prepare("SELECT id FROM districts WHERE province_id=? ORDER BY RAND() LIMIT 1");
      $rd->bind_param('i', $province);
      $rd->execute(); $dres = $rd->get_result(); $drow = $dres->fetch_assoc(); $rd->close();
      if ($drow) {
        $district = (int)$drow['id'];
        $rc = db()->prepare("SELECT id FROM cities WHERE district_id=? ORDER BY RAND() LIMIT 1");
        $rc->bind_param('i', $district);
        $rc->execute(); $cres = $rc->get_result(); $crow = $cres->fetch_assoc(); $rc->close();
        if ($crow) { $city = (int)$crow['id']; }
      }
    }
  } catch (Throwable $e) {}
  return [$province ?: 1, $district ?: 1, $city ?: 1];
}

function sample_title(): string {
  $adjs = ['Spacious','Cozy','Modern','Luxury','Affordable','Renovated','Bright','Quiet','Central','Charming'];
  $types = ['House','Apartment','Villa','Studio','Townhouse','Bungalow'];
  return $adjs[array_rand($adjs)] . ' ' . $types[array_rand($types)];
}

function sample_desc(): string {
  $phr = [
    'Close to public transport and shops.',
    'Newly renovated with modern amenities.',
    'Great neighborhood with parks nearby.',
    'Ideal for families or professionals.',
    'Plenty of natural light and storage.',
    'Move-in ready and well maintained.'
  ];
  return implode(' ', [ $phr[array_rand($phr)], $phr[array_rand($phr)], $phr[array_rand($phr)] ]);
}

function pick_bool(): int { return (mt_rand(0,1) === 1) ? 1 : 0; }

function pick_property_type(): string {
  $allowed = ['apartment','house','villa','duplex','studio','penthouse','bungalow','townhouse','farmhouse','office','shop','warehouse','land','commercial_building','industrial','hotel','guesthouse','resort','other'];
  return $allowed[array_rand($allowed)];
}

function ensure_dir(string $dir): void { if (!is_dir($dir)) { @mkdir($dir, 0775, true); } }

function pick_sample_images(): array {
  $srcDir = __DIR__ . '/images/property_images';
  $files = @scandir($srcDir) ?: [];
  $imgs = [];
  foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $srcDir . '/' . $f;
    if (is_file($p)) $imgs[] = $p;
  }
  if (empty($imgs)) return [];
  shuffle($imgs);
  $count = mt_rand(1, 4);
  return array_slice($imgs, 0, $count);
}

function copy_image_to_uploads(string $src, int $prop_id, int $idx = 0): ?array {
  $uploads = dirname(__DIR__) . '/uploads/properties';
  ensure_dir($uploads);
  $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'jpg';
  $name = 'prop_' . $prop_id . ($idx ? ('_' . $idx) : '') . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
  $dest = $uploads . '/' . $name;
  if (@copy($src, $dest)) {
    $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $name;
    return [$dest, $rel];
  }
  return null;
}

$count = max(1, min(200, (int)($_POST['count'] ?? 0)));
$owner_id_in = isset($_POST['owner_id']) && $_POST['owner_id'] !== '' ? (int)$_POST['owner_id'] : null;
// status selection
$allowed_status = ['pending','available','unavailable'];
$status_in = strtolower((string)($_POST['status'] ?? 'pending'));
if (!in_array($status_in, $allowed_status, true)) { $status_in = 'pending'; }

$created = 0; $errors = 0; $created_ids = [];
for ($i=0; $i<$count; $i++) {
  $owner_id = pick_owner($owner_id_in);
  if (!$owner_id) { $errors++; continue; }
  [$province_id, $district_id, $city_id] = pick_location();

  $title = sample_title();
  $desc = sample_desc();
  $price = mt_rand(15000, 250000);
  $bed = mt_rand(0, 5);
  $bath = mt_rand(1, 4);
  $living = mt_rand(0, 2);
  $sqft = mt_rand(400, 3500);
  $ptype = pick_property_type();
  $address = 'No. ' . mt_rand(1, 250) . ', Test Street';
  $postal = (string)mt_rand(10000, 99999);

  try {
    $code = 'TEMP-' . bin2hex(random_bytes(4));
    $status = $status_in;
    $stmt = db()->prepare('INSERT INTO properties (owner_id, property_code, title, description, price_per_month, bedrooms, bathrooms, living_rooms, garden, gym, pool, sqft, kitchen, parking, water_supply, electricity_supply, property_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $kitchen = pick_bool(); $parking = pick_bool(); $water = 1; $electric = 1; $garden = pick_bool(); $gym = pick_bool(); $pool = pick_bool();
    $stmt->bind_param('isssdiiiiiidiiiiss', $owner_id, $code, $title, $desc, $price, $bed, $bath, $living, $garden, $gym, $pool, $sqft, $kitchen, $parking, $water, $electric, $ptype, $status);
    if (!$stmt->execute()) { $errors++; $stmt->close(); continue; }
    $new_id = db()->insert_id; $stmt->close();
    // Pretty code
    try { $final = 'PROP-' . str_pad((string)$new_id, 6, '0', STR_PAD_LEFT); $up = db()->prepare('UPDATE properties SET property_code=? WHERE property_id=?'); $up->bind_param('si', $final, $new_id); $up->execute(); $up->close(); } catch (Throwable $e) {}
    // Location
    try { $loc = db()->prepare('INSERT INTO locations (property_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)'); $loc->bind_param('iiiiss', $new_id, $province_id, $district_id, $city_id, $address, $postal); $loc->execute(); $loc->close(); } catch (Throwable $e) {}
    // Images
    $imgs = pick_sample_images(); $idx = 0; $primary_set = false;
    foreach ($imgs as $img) {
      $idx++;
      $copied = copy_image_to_uploads($img, $new_id, $idx);
      if (!$copied) continue;
      [$abs, $url] = $copied;
      $is_primary = $primary_set ? 0 : 1;
      try { $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, ?)'); $pi->bind_param('isi', $new_id, $url, $is_primary); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
      if (!$primary_set) {
        try { $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?'); $up->bind_param('si', $url, $new_id); $up->execute(); $up->close(); } catch (Throwable $e) {}
        $primary_set = true;
      }
    }
    $created++; $created_ids[] = (int)$new_id;
  } catch (Throwable $e) {
    $errors++;
  }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generated Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:900px;">
    <h1 class="h4 mb-3">Property Generator Result</h1>
    <ul class="list-group mb-3">
      <li class="list-group-item">Requested: <?php echo (int)$count; ?></li>
      <li class="list-group-item">Created: <?php echo (int)$created; ?></li>
      <li class="list-group-item">Errors: <?php echo (int)$errors; ?></li>
    </ul>
    <?php if (!empty($created_ids)): ?>
      <div class="mb-3">Created property IDs: <?php echo htmlspecialchars(implode(', ', $created_ids)); ?></div>
    <?php endif; ?>
    <a class="btn btn-secondary" href="index.php">Back to Generator</a>
  </div>
</body>
</html>

