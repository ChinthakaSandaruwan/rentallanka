<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100; $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096); $read = $pos - $step;
        fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
  }
}

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

// Serve geo endpoints similar to create
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

$room_id = (int)($_GET['id'] ?? $_POST['room_id'] ?? 0);
$error = '';
$flash = '';
$flash_type = '';
$room = null;
$location = ['province_id'=>null,'district_id'=>null,'city_id'=>null,'address'=>'','google_map_link'=>'','postal_code'=>''];
$primary_image_path = '';
$images = [];

// Load room + location for GET (and for POST re-check ownership)
if ($room_id > 0) {
  $sql = 'SELECT r.room_id, r.title, r.description, r.room_type, r.beds, r.maximum_guests, r.price_per_day, r.status
          FROM rooms r WHERE r.room_id=? AND r.owner_id=?';
  $st = db()->prepare($sql);
  $st->bind_param('ii', $room_id, $uid);
  $st->execute();
  $rs = $st->get_result();
  $room = $rs->fetch_assoc() ?: null;
  $st->close();

  if ($room) {
    $ls = db()->prepare('SELECT province_id, district_id, city_id, address, google_map_link, postal_code FROM room_locations WHERE room_id=? LIMIT 1');
    $ls->bind_param('i', $room_id);
    $ls->execute();
    $lr = $ls->get_result()->fetch_assoc();
    $ls->close();
    if ($lr) { $location = $lr; }

    $ps = db()->prepare('SELECT image_path FROM room_images WHERE room_id=? AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1');
    $ps->bind_param('i', $room_id);
    $ps->execute();
    $pr = $ps->get_result()->fetch_assoc();
    $ps->close();
    if ($pr) { $primary_image_path = (string)$pr['image_path']; }

    // Load all images for management UI
    $gi = db()->prepare('SELECT image_path, COALESCE(is_primary,0) AS is_primary FROM room_images WHERE room_id=? ORDER BY uploaded_at DESC');
    $gi->bind_param('i', $room_id);
    $gi->execute();
    $gr = $gi->get_result();
    while ($row = $gr->fetch_assoc()) { $images[] = $row; }
    $gi->close();

    // Load per-room meal price overrides
    try {
      $mo = db()->prepare('SELECT meal_name, price FROM room_meals WHERE room_id=?');
      $mo->bind_param('i', $room_id);
      $mo->execute();
      $mr = $mo->get_result();
      while ($row = $mr->fetch_assoc()) {
        $meal_overrides[strtolower((string)$row['meal_name'])] = (float)$row['price'];
      }
      $mo->close();
    } catch (Throwable $e) { $meal_overrides = []; }
  }
}

// After POST handling, if there is an error, redirect with flash to avoid resubmission warning
if (!empty($error)) {
  redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_update.php?id=' . (int)$room_id, $error, 'error');
  exit;
}

// If no id provided, prepare a list of user's rooms for selection
$owner_rooms = [];
if ($room_id <= 0) {
  $qs = db()->prepare('SELECT room_id, title, price_per_day FROM rooms WHERE owner_id=? ORDER BY created_at DESC');
  $qs->bind_param('i', $uid);
  $qs->execute();
  $rr = $qs->get_result();
  while ($row = $rr->fetch_assoc()) { $owner_rooms[] = $row; }
  $qs->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $room_id = (int)($_POST['room_id'] ?? 0);
    // Ensure ownership
    $st = db()->prepare('SELECT room_id FROM rooms WHERE room_id=? AND owner_id=?');
    $st->bind_param('ii', $room_id, $uid);
    $st->execute();
    $owned = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    if (!$owned) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_update.php', 'Room not found', 'error');
    }

    // Handle image actions early
    $image_action = $_POST['image_action'] ?? '';
    if ($image_action === 'make_primary' || $image_action === 'delete_image') {
      $path = $_POST['image_path'] ?? '';
      if ($path !== '') {
        if ($image_action === 'make_primary') {
          // Set all to non-primary, then set provided path to primary
          $clr = db()->prepare('UPDATE room_images SET is_primary=0 WHERE room_id=?');
          $clr->bind_param('i', $room_id);
          $clr->execute();
          $clr->close();

          $mk = db()->prepare('UPDATE room_images SET is_primary=1 WHERE room_id=? AND image_path=?');
          $mk->bind_param('is', $room_id, $path);
          $mk->execute();
          $mk->close();

          redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_update.php?id=' . (int)$room_id, 'Primary image updated.', 'success');
        } elseif ($image_action === 'delete_image') {
          // Remove DB record
          $del = db()->prepare('DELETE FROM room_images WHERE room_id=? AND image_path=?');
          $del->bind_param('is', $room_id, $path);
          $del->execute();
          $affected = $del->affected_rows;
          $del->close();

          // Delete file if it belongs to uploads path
          if ($affected > 0) {
            $prefix = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/';
            $root = dirname(__DIR__, 2) . '/uploads/rooms/';
            if (strpos($path, $prefix) === 0) {
              $fname = substr($path, strlen($prefix));
              $full = $root . $fname;
              if (is_file($full)) { @unlink($full); }
            }
          }

          // Ensure there is a primary; if none remains, set latest as primary
          $chk = db()->prepare('SELECT image_path FROM room_images WHERE room_id=? AND is_primary=1 LIMIT 1');
          $chk->bind_param('i', $room_id);
          $chk->execute();
          $hasPrimary = (bool)$chk->get_result()->fetch_assoc();
          $chk->close();
          if (!$hasPrimary) {
            $pick = db()->prepare('SELECT image_path FROM room_images WHERE room_id=? ORDER BY uploaded_at DESC LIMIT 1');
            $pick->bind_param('i', $room_id);
            $pick->execute();
            $row = $pick->get_result()->fetch_assoc();
            $pick->close();
            if ($row) {
              $mk = db()->prepare('UPDATE room_images SET is_primary=1 WHERE room_id=? AND image_path=?');
              $mk->bind_param('is', $room_id, $row['image_path']);
              $mk->execute();
              $mk->close();
            }
          }

          redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_update.php?id=' . (int)$room_id, 'Image deleted.', 'success');
        }
      }
      // Fallthrough if no path
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $room_type = $_POST['room_type'] ?? 'other';
    $beds = (int)($_POST['beds'] ?? 1);
    $maximum_guests = (int)($_POST['maximum_guests'] ?? 1);
    $price_per_day_raw = $_POST['price_per_day'] ?? '';
    $price_per_day = ($price_per_day_raw === '' ? null : (float)$price_per_day_raw);

    $province_id = (int)($_POST['province_id'] ?? 0);
    $district_id = (int)($_POST['district_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $google_map_link = trim($_POST['google_map_link'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

    $allowed_types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
    if (!in_array($room_type, $allowed_types, true)) { $room_type = 'other'; }

    if ($title === '' || $price_per_day === null || $price_per_day <= 0) {
      $error = 'Title and price per day are required';
    } elseif ($province_id <= 0 || $district_id <= 0 || $city_id <= 0 || $postal_code === '') {
      $error = 'Location (province, district, city, postal code) is required';
    } elseif (mb_strlen($title) > 150) {
      $error = 'Title is too long';
    } elseif (mb_strlen($postal_code) > 10) {
      $error = 'Postal code is too long';
    } elseif (mb_strlen($address) > 255) {
      $error = 'Address is too long';
    } elseif (mb_strlen($google_map_link) > 255) {
      $error = 'Google map link is too long';
    } elseif ($beds < 1 || $maximum_guests < 1) {
      $error = 'Beds and maximum guests must be at least 1';
    } else {
      // Update main room fields
      $up = db()->prepare('UPDATE rooms SET title=?, description=?, room_type=?, beds=?, maximum_guests=?, price_per_day=? WHERE room_id=? AND owner_id=?');
      $up->bind_param('sssiidii', $title, $description, $room_type, $beds, $maximum_guests, $price_per_day, $room_id, $uid);
      $ok = $up->execute();
      $up->close();

      if ($ok) {
        // Upsert location
        $exists = false;
        $ch = db()->prepare('SELECT 1 FROM room_locations WHERE room_id=? LIMIT 1');
        $ch->bind_param('i', $room_id);
        $ch->execute();
        $exists = (bool)$ch->get_result()->fetch_row();
        $ch->close();
        if ($exists) {
          $loc = db()->prepare('UPDATE room_locations SET province_id=?, district_id=?, city_id=?, address=?, google_map_link=?, postal_code=? WHERE room_id=?');
          $gmap = ($google_map_link === '' ? null : $google_map_link);
          $loc->bind_param('iiisssi', $province_id, $district_id, $city_id, $address, $gmap, $postal_code, $room_id);
          $loc->execute();
          $loc->close();
        } else {
          $loc = db()->prepare('INSERT INTO room_locations (room_id, province_id, district_id, city_id, address, google_map_link, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
          $gmap = ($google_map_link === '' ? null : $google_map_link);
          $loc->bind_param('iiiisss', $room_id, $province_id, $district_id, $city_id, $address, $gmap, $postal_code);
          $loc->execute();
          $loc->close();
        }

        // File uploads
        $dir = dirname(__DIR__, 2) . '/uploads/rooms';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        // Replace primary image if provided
        if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
          $imgSize = (int)($_FILES['image']['size'] ?? 0);
          $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
          if ($imgSize > 0 && $imgSize <= 5242880 && $imgInfo !== false) {
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
            $fname = 'room_' . $room_id . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
              resize_image_constrain($dest, 1600, 1200);
              $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
              // Fetch current primary image to unmark and unlink
              $old = null;
              $gp = db()->prepare('SELECT image_path FROM room_images WHERE room_id=? AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1');
              $gp->bind_param('i', $room_id);
              $gp->execute();
              $or = $gp->get_result()->fetch_assoc();
              $gp->close();
              if ($or) { $old = (string)$or['image_path']; }

              // Set previous as non-primary
              $clr = db()->prepare('UPDATE room_images SET is_primary=0 WHERE room_id=?');
              $clr->bind_param('i', $room_id);
              $clr->execute();
              $clr->close();

              // Insert new primary
              try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)'); $pi->bind_param('is', $room_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}

              // Try unlink old file
              if ($old) {
                $prefix = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/';
                $root = dirname(__DIR__, 2) . '/uploads/rooms/';
                if (strpos($old, $prefix) === 0) {
                  $fname = substr($old, strlen($prefix));
                  $full = $root . $fname;
                  if (is_file($full)) { @unlink($full); }
                }
              }
            }
          }
        }

        // Add any new gallery images
        if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
          $count = count($_FILES['gallery_images']['name']);
          for ($i=0; $i < $count; $i++) {
            if (empty($_FILES['gallery_images']['name'][$i]) || !is_uploaded_file($_FILES['gallery_images']['tmp_name'][$i])) { continue; }
            $gSize = (int)($_FILES['gallery_images']['size'][$i] ?? 0);
            $gInfo = @getimagesize($_FILES['gallery_images']['tmp_name'][$i]);
            if ($gSize <= 0 || $gSize > 5242880 || $gInfo === false) { continue; }
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
            $fname = 'room_' . $room_id . '_' . ($i+1) . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
              $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
              try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)'); $pi->bind_param('is', $room_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
            }
          }
        }

        // Upsert per-room meal price overrides (blank deletes)
        try {
          // Static mapping; meal_plans table removed
          $mealsMap = [
            'breakfast' => 1,
            'half_board' => 2,
            'full_board' => 3,
            'all_inclusive' => 4,
          ];
          $pairs = [
            'breakfast' => $_POST['meal_price_breakfast'] ?? null,
            'half_board' => $_POST['meal_price_half_board'] ?? null,
            'full_board' => $_POST['meal_price_full_board'] ?? null,
            'all_inclusive' => $_POST['meal_price_all_inclusive'] ?? null,
          ];
          foreach ($pairs as $name => $val) {
            $mid = (int)($mealsMap[$name] ?? 0);
            if ($mid <= 0) { continue; }
            if ($val === null || $val === '') {
              // Blank means delete override
              $del = db()->prepare('DELETE FROM room_meals WHERE room_id=? AND meal_id=?');
              $del->bind_param('ii', $room_id, $mid);
              $del->execute();
              $del->close();
            } elseif (is_numeric($val)) {
              $price = (float)$val; if ($price < 0) { $price = 0.0; }
              // Upsert into room_meals
              $upsert = db()->prepare('INSERT INTO room_meals (room_id, meal_id, meal_name, price) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE meal_name=VALUES(meal_name), price=VALUES(price)');
              $upsert->bind_param('iisd', $room_id, $mid, $name, $price);
              $upsert->execute();
              $upsert->close();
            }
          }
        } catch (Throwable $e) { /* ignore */ }

        redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_update.php?id=' . (int)$room_id, 'Room updated successfully.', 'success');
        exit;
      } else {
        $error = 'Failed to update room';
      }
    }
  }
}

[$flash, $flash_type] = [$flash ?: (get_flash()[0] ?? ''), $flash_type ?: (get_flash()[1] ?? '')];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Update Room</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.5rem 2rem; margin-bottom:2rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.5rem,3vw,1.875rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }
    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1.25rem 1.5rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1.125rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:2rem 1.5rem; }
    .form-label { font-weight:600; color:var(--rl-text); margin-bottom:.5rem; font-size:.9375rem; }
    .form-control,.form-select { border:2px solid var(--rl-border); border-radius:10px; padding:.75rem 1rem; font-size:1rem; color:var(--rl-text); background:var(--rl-white); transition:all .2s ease; font-weight:500; }
    .form-control::placeholder { color:#a0aec0; font-weight:400; }
    .form-control:focus,.form-select:focus { border-color:var(--rl-primary); box-shadow:0 0 0 3px rgba(0,78,152,.1); outline:none; background:var(--rl-white); }
    .form-control:hover:not(:focus),.form-select:hover:not(:focus) { border-color:#cbd5e0; }
    .input-group-text { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); border-radius:10px 0 0 10px; padding:.75rem 1rem; font-size:1rem; min-width:50px; display:flex; align-items:center; justify-content:center; }
    .input-group .form-control { border-left:none; border-radius:0 10px 10px 0; }
    .btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; padding:.875rem 2.5rem; border-radius:50rem; font-size:1rem; transition:all .2s ease; box-shadow:0 4px 16px rgba(0,78,152,.25); }
    .btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-2px); box-shadow:0 6px 24px rgba(0,78,152,.35); color:var(--rl-white); }
    .invalid-feedback { color:#ef4444; font-weight:600; font-size:.875rem; margin-top:.5rem; }
    .form-control.is-invalid,.form-select.is-invalid { border-color:#ef4444; }
    .form-control.is-invalid:focus,.form-select.is-invalid:focus { border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.1); }
    @media (max-width:767px){ .rl-page-header{ padding:1.25rem 1rem; flex-direction:column; align-items:flex-start;} .rl-page-title{ font-size:1.5rem;} .rl-btn-back{ width:100%; justify-content:center;} .rl-form-body{ padding:1.5rem 1rem;} .form-control,.form-select{ font-size:.9375rem; padding:.625rem .875rem;} .btn-primary{ width:100%; padding:.75rem 2rem;} }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-door-open"></i> Update Room</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php /* Flash and errors are shown via SweetAlert2 (navbar); removed Bootstrap alerts */ ?>

  <?php if (!$room): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-search"></i> Select a Room to Update</h2></div>
      <div class="rl-form-body">
        <?php if (!empty($owner_rooms)): ?>
          <form method="get" class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Your Rooms</label>
              <select name="id" class="form-select" required>
                <option value="">Select a room</option>
                <?php foreach ($owner_rooms as $r): ?>
                  <option value="<?php echo (int)$r['room_id']; ?>">
                    <?php echo htmlspecialchars(($r['title'] ?: 'Untitled') . ' — LKR ' . number_format((float)($r['price_per_day'] ?? 0), 2)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary">Load Room</button>
            </div>
          </form>
        <?php else: ?>
          <div class="text-muted mb-0">You have no rooms to update yet.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
  <div id="formAlert"></div>
  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-info-circle"></i> Room Details</h2></div>
    <div class="rl-form-body">
      <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="room_id" value="<?php echo (int)$room_id; ?>">
        <div class="mb-3">
          <label class="form-label">Title</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
            <input name="title" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($room['title'] ?? ''); ?>" placeholder="Cozy single room">
            <div class="invalid-feedback">Please enter a title (max 150 characters).</div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" rows="3" class="form-control" placeholder="Describe the room, amenities, nearby places..."><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Province</label>
            <select name="province_id" id="province" class="form-select" required data-current="<?php echo (int)($location['province_id'] ?? 0); ?>"></select>
            <div class="invalid-feedback">Please select a province.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">District</label>
            <select name="district_id" id="district" class="form-select" required data-current="<?php echo (int)($location['district_id'] ?? 0); ?>"></select>
            <div class="invalid-feedback">Please select a district.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">City</label>
            <select name="city_id" id="city" class="form-select" required data-current="<?php echo (int)($location['city_id'] ?? 0); ?>"></select>
            <div class="invalid-feedback">Please select a city.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Postal Code</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-mailbox"></i></span>
              <input name="postal_code" class="form-control" required maxlength="10" value="<?php echo htmlspecialchars($location['postal_code'] ?? ''); ?>" placeholder="e.g. 10115">
              <div class="invalid-feedback">Please provide a postal code.</div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input name="address" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($location['address'] ?? ''); ?>" placeholder="Street, number, etc.">
          </div>
          <div class="col-12">
            <label class="form-label">Google Map Link (optional)</label>
            <input name="google_map_link" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($location['google_map_link'] ?? ''); ?>" placeholder="https://maps.google.com/...">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Price per day (LKR)</label>
          <div class="input-group">
            <span class="input-group-text">LKR</span>
            <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required placeholder="0.00" value="<?php echo htmlspecialchars((string)($room['price_per_day'] ?? '')); ?>">
            <div class="invalid-feedback">Please enter a price greater than 0.</div>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col">
            <label class="form-label">Beds</label>
            <input name="beds" type="number" min="1" class="form-control" placeholder="1" value="<?php echo (int)($room['beds'] ?? 1); ?>">
          </div>
          <div class="col">
            <label class="form-label">Maximum guests</label>
            <input name="maximum_guests" type="number" min="1" class="form-control" placeholder="1" value="<?php echo (int)($room['maximum_guests'] ?? 1); ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Room type</label>
          <select name="room_type" class="form-select">
            <?php
              $types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
              $curType = (string)($room['room_type'] ?? 'other');
              foreach ($types as $t) {
                $sel = ($curType === $t) ? ' selected' : '';
                echo '<option value="'.htmlspecialchars($t).'"'.$sel.'>'.ucwords(str_replace('_',' ',$t))."</option>";
              }
            ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Meal prices per day (optional)</label>
          <div class="row g-2">
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">Breakfast</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_breakfast" value="<?php echo htmlspecialchars(isset($meal_overrides['breakfast']) ? (string)$meal_overrides['breakfast'] : ''); ?>" placeholder="e.g. 1000.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">Half board</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_half_board" value="<?php echo htmlspecialchars(isset($meal_overrides['half_board']) ? (string)$meal_overrides['half_board'] : ''); ?>" placeholder="e.g. 1500.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">Full board</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_full_board" value="<?php echo htmlspecialchars(isset($meal_overrides['full_board']) ? (string)$meal_overrides['full_board'] : ''); ?>" placeholder="e.g. 2000.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">All inclusive</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_all_inclusive" value="<?php echo htmlspecialchars(isset($meal_overrides['all_inclusive']) ? (string)$meal_overrides['all_inclusive'] : ''); ?>" placeholder="e.g. 3000.00">
              </div>
            </div>
          </div>
          <div class="form-text">Leave blank to use global meal plan prices. Only meals up to the selected meal plan capability will be kept.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Primary Image (optional, max 5MB)</label>
          <?php if (!empty($primary_image_path)): ?>
            <div class="mb-2"><img src="<?php echo htmlspecialchars($primary_image_path); ?>" alt="Current" style="max-height:120px"></div>
          <?php endif; ?>
          <input type="file" name="image" accept="image/*" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Add Gallery Images (optional, max 5MB each)</label>
          <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
        </div>
        <button type="submit" class="btn btn-primary">Update Room</button>
      </form>
      <div class="mt-4">
        <label class="form-label">Manage Existing Images</label>
        <?php if (!empty($images)): ?>
          <div class="row g-3">
            <?php foreach ($images as $im): ?>
              <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100">
                  <?php if (!empty($im['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($im['image_path']); ?>" class="card-img-top" alt="Room image" style="object-fit:cover; height:180px">
                  <?php endif; ?>
                  <div class="card-body d-flex flex-column">
                    <div class="mb-2">
                      <?php if (!empty($im['is_primary'])): ?>
                        <span class="badge bg-success">Primary</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Gallery</span>
                      <?php endif; ?>
                    </div>
                    <div class="mt-auto d-flex gap-2">
                      <?php if (empty($im['is_primary'])): ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="room_id" value="<?php echo (int)$room_id; ?>">
                          <input type="hidden" name="image_action" value="make_primary">
                          <input type="hidden" name="image_path" value="<?php echo htmlspecialchars($im['image_path']); ?>">
                          <button type="submit" class="btn btn-outline-primary btn-sm">Make Primary</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" class="d-inline room-image-del-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="room_id" value="<?php echo (int)$room_id; ?>">
                        <input type="hidden" name="image_action" value="delete_image">
                        <input type="hidden" name="image_path" value="<?php echo htmlspecialchars($im['image_path']); ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted">No images uploaded yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.room-image-del-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const imgEl = form.closest('.card')?.querySelector('img.card-img-top');
          const label = imgEl ? 'this image' : 'image';
          const res = await Swal.fire({
            title: 'Delete image?',
            text: 'This action cannot be undone. Delete ' + label + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
<script src="js/room_update.js" defer></script>
</body>
</html>

