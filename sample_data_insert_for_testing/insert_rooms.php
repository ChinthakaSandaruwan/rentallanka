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
  $adjs = ['Cozy','Bright','Quiet','Modern','Clean','Spacious','Budget','Premium'];
  $types = ['Single Room','Double Room','Twin Room','Suite','Deluxe Room','Studio'];
  return $adjs[array_rand($adjs)] . ' ' . $types[array_rand($types)];
}

function sample_desc(): string {
  $phr = [
    'Includes basic furnishings.',
    'Near bus routes and shops.',
    'High-speed internet available.',
    'Calm neighborhood.',
    'Good ventilation and lighting.'
  ];
  return implode(' ', [ $phr[array_rand($phr)], $phr[array_rand($phr)], $phr[array_rand($phr)] ]);
}

function pick_room_type(): string {
  $allowed = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
  return $allowed[array_rand($allowed)];
}

function ensure_dir(string $dir): void { if (!is_dir($dir)) { @mkdir($dir, 0775, true); } }

function pick_sample_images(): array {
  $srcDir = __DIR__ . '/images/room_images';
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

function copy_image_to_uploads(string $src, int $room_id, int $idx = 0): ?array {
  $uploads = dirname(__DIR__) . '/uploads/rooms';
  ensure_dir($uploads);
  $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'jpg';
  $name = 'room_' . $room_id . ($idx ? ('_' . $idx) : '') . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/','', $ext);
  $dest = $uploads . '/' . $name;
  if (@copy($src, $dest)) {
    $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $name;
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
  $room_type = pick_room_type();
  $beds = mt_rand(1, 3);
  $guests = max($beds, mt_rand(1, 4));
  $price_day = mt_rand(1500, 15000);
  $address = 'No. ' . mt_rand(1, 250) . ', Test Street';
  $postal = (string)mt_rand(10000, 99999);

  try {
    $code = 'TEMP-' . bin2hex(random_bytes(4));
    $status = $status_in;
    $stmt = db()->prepare('INSERT INTO rooms (room_code, owner_id, title, description, room_type, beds, maximum_guests, price_per_day, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sisssiids', $code, $owner_id, $title, $desc, $room_type, $beds, $guests, $price_day, $status);
    if (!$stmt->execute()) { $errors++; $stmt->close(); continue; }
    $new_id = db()->insert_id; $stmt->close();
    try { $final = 'ROOM-' . str_pad((string)$new_id, 6, '0', STR_PAD_LEFT); $up = db()->prepare('UPDATE rooms SET room_code=? WHERE room_id=?'); $up->bind_param('si', $final, $new_id); $up->execute(); $up->close(); } catch (Throwable $e) {}
    try { $loc = db()->prepare('INSERT INTO locations (room_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)'); $loc->bind_param('iiiiss', $new_id, $province_id, $district_id, $city_id, $address, $postal); $loc->execute(); $loc->close(); } catch (Throwable $e) {}
    $imgs = pick_sample_images(); $idx = 0; $primary_set = false;
    foreach ($imgs as $img) {
      $idx++;
      $copied = copy_image_to_uploads($img, $new_id, $idx);
      if (!$copied) continue;
      [$abs, $url] = $copied;
      $is_primary = $primary_set ? 0 : 1;
      try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)'); $pi->bind_param('isi', $new_id, $url, $is_primary); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
      if (!$primary_set) { $primary_set = true; }
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
  <title>Generated Rooms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:900px;">
    <h1 class="h4 mb-3">Room Generator Result</h1>
    <ul class="list-group mb-3">
      <li class="list-group-item">Requested: <?php echo (int)$count; ?></li>
      <li class="list-group-item">Created: <?php echo (int)$created; ?></li>
      <li class="list-group-item">Errors: <?php echo (int)$errors; ?></li>
    </ul>
    <?php if (!empty($created_ids)): ?>
      <div class="mb-3">Created room IDs: <?php echo htmlspecialchars(implode(', ', $created_ids)); ?></div>
    <?php endif; ?>
    <a class="btn btn-secondary" href="index.php">Back to Generator</a>
  </div>
</body>
</html>

