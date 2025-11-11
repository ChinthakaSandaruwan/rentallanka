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

// Guard: require active, paid room package with remaining slots
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  try {
    $q = db()->prepare("SELECT bp.bought_package_id, bp.remaining_rooms, bp.end_date
                         FROM bought_packages bp
                         JOIN packages p ON p.package_id = bp.package_id
                         WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                           AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                           AND COALESCE(p.max_rooms,0) > 0
                           AND COALESCE(bp.remaining_rooms,0) > 0
                         ORDER BY bp.start_date DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute();
    $pkg = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$pkg) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a room package (paid & active) with remaining slots before creating a room.', 'error');
    }
  } catch (Throwable $e) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a room package before creating a room.', 'error');
  }
}

// Fetch remaining room slots to display
$remaining_room_slots = null;
try {
  $st = db()->prepare("SELECT bp.remaining_rooms
                        FROM bought_packages bp
                        JOIN packages p ON p.package_id = bp.package_id
                        WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                          AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                          AND COALESCE(p.max_rooms,0) > 0
                        ORDER BY bp.start_date DESC LIMIT 1");
  $st->bind_param('i', $uid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  if ($r) { $remaining_room_slots = (int)$r['remaining_rooms']; }
} catch (Throwable $e) { /* ignore */ }

$error = '';
$flash = '';
$flash_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    // Check active paid package with room slots
    $bp = null; $bp_id = 0; $rem_rooms = 0;
    try {
      $q = db()->prepare("SELECT bp.bought_package_id, bp.remaining_rooms, bp.end_date
                           FROM bought_packages bp
                           JOIN packages p ON p.package_id = bp.package_id
                           WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                             AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                             AND COALESCE(p.max_rooms,0) > 0
                           ORDER BY bp.start_date DESC LIMIT 1");
      $q->bind_param('i', $uid);
      $q->execute();
      $bp = $q->get_result()->fetch_assoc();
      $q->close();
      if ($bp) { $bp_id = (int)$bp['bought_package_id']; $rem_rooms = (int)$bp['remaining_rooms']; }
    } catch (Throwable $e) { /* ignore */ }
    if ($bp_id <= 0) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a room package and complete payment before adding a room.', 'error');
    }
    if ($rem_rooms <= 0) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Your package does not have remaining room slots.', 'error');
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
      $room_code = 'TEMP-' . bin2hex(random_bytes(4));
      $stmt = db()->prepare('INSERT INTO rooms (room_code, owner_id, title, description, room_type, beds, maximum_guests, price_per_day, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $pending_status = 'pending';
      $stmt->bind_param('sisssiids', $room_code, $uid, $title, $description, $room_type, $beds, $maximum_guests, $price_per_day, $pending_status);
      if ($stmt->execute()) {
        $new_id = db()->insert_id;
        $stmt->close();
        try {
          $final_code = 'ROOM-' . str_pad((string)$new_id, 6, '0', STR_PAD_LEFT);
          $upc = db()->prepare('UPDATE rooms SET room_code=? WHERE room_id=?');
          $upc->bind_param('si', $final_code, $new_id);
          $upc->execute();
          $upc->close();
        } catch (Throwable $e) {}
        try {
          $gmap = ($google_map_link === '' ? null : $google_map_link);
          $loc = db()->prepare('INSERT INTO room_locations (room_id, province_id, district_id, city_id, address, google_map_link, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
          $loc->bind_param('iiiisss', $new_id, $province_id, $district_id, $city_id, $address, $gmap, $postal_code);
          $loc->execute();
          $loc->close();
        } catch (Throwable $e) {}
        $dir = dirname(__DIR__, 2) . '/uploads/rooms';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
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
            $fname = 'room_' . $new_id . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
              resize_image_constrain($dest, 1600, 1200);
              $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
              try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)'); $pi->bind_param('is', $new_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
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
            $fname = 'room_' . $new_id . '_' . ($i+1) . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
              $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
              try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)'); $pi->bind_param('is', $new_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
            }
          }
        }
        // Insert per-room meal price overrides (optional)
        try {
          // Use static mapping as we no longer rely on meal_plans table
          $mealsMap = [
            'breakfast' => 1,
            'half_board' => 2,
            'full_board' => 3,
            'all_inclusive' => 4,
          ];
          $order = ['none'=>0,'breakfast'=>1,'half_board'=>2,'full_board'=>3,'all_inclusive'=>4];
          $capLevel = 4;
          $pairs = [
            'breakfast' => $_POST['meal_price_breakfast'] ?? '',
            'half_board' => $_POST['meal_price_half_board'] ?? '',
            'full_board' => $_POST['meal_price_full_board'] ?? '',
            'all_inclusive' => $_POST['meal_price_all_inclusive'] ?? '',
          ];
          foreach ($pairs as $name => $val) {
            $lvl = $order[$name] ?? 0;
            if ($lvl < 1 || $lvl > $capLevel) { continue; }
            if ($val === '' || !is_numeric($val)) { continue; }
            $price = (float)$val; if ($price < 0) { $price = 0.0; }
            $mid = $mealsMap[$name] ?? 0; if ($mid <= 0) { continue; }
            $insMp = db()->prepare('INSERT INTO room_meals (room_id, meal_id, meal_name, price) VALUES (?,?,?,?)');
            $insMp->bind_param('iisd', $new_id, $mid, $name, $price);
            $insMp->execute();
            $insMp->close();
          }
        } catch (Throwable $e) { /* ignore meal override errors */ }

        // Deduct one room slot from the purchased package
        try {
          if (!empty($bp_id) && $bp_id > 0) {
            $dec = db()->prepare('UPDATE bought_packages SET remaining_rooms = remaining_rooms - 1 WHERE bought_package_id = ? AND remaining_rooms > 0');
            $dec->bind_param('i', $bp_id);
            $dec->execute();
            $dec->close();
          }
        } catch (Throwable $e) { /* ignore deduction failure */ }

        // Reflect new remaining slots in the UI without re-query
        if ($remaining_room_slots !== null && $remaining_room_slots > 0) {
          $remaining_room_slots = $remaining_room_slots - 1;
        }

        redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_create.php', 'Room created successfully.', 'success');
        exit;
      } else {
        $error = 'Failed to create room';
        $stmt->close();
      }
    }
  }
}

[$flash, $flash_type] = [$flash ?: (get_flash()[0] ?? ''), $flash_type ?: (get_flash()[1] ?? '')];
if (!empty($error)) {
  redirect_with_message($GLOBALS['base_url'] . '/owner/room/room_create.php', $error, 'error');
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Room</title>
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
    .rl-slots-badge { background:linear-gradient(135deg,var(--rl-dark) 0%, #ff8533 100%); color:var(--rl-white); padding:.5rem 1.25rem; border-radius:50px; font-weight:700; font-size:.9375rem; display:inline-flex; align-items:center; gap:.5rem; box-shadow:var(--rl-shadow-sm); }
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
    .form-check { padding:.75rem 1rem; background:#f8fafc; border-radius:8px; border:2px solid var(--rl-border); transition:all .2s ease; }
    .form-check:hover { background:#f1f5f9; border-color:var(--rl-accent); }
    .form-check-input { width:1.25rem; height:1.25rem; border:2px solid var(--rl-border); border-radius:4px; margin-top:.125rem; }
    .form-check-input:checked { background-color:var(--rl-primary); border-color:var(--rl-primary); }
    .form-check-input:focus { border-color:var(--rl-primary); box-shadow:0 0 0 3px rgba(0,78,152,.1); }
    .form-check-label { font-weight:600; color:var(--rl-text); font-size:.9375rem; cursor:pointer; }
    textarea.form-control { resize:vertical; min-height:100px; }
    input[type="file"].form-control { padding:.625rem 1rem; cursor:pointer; }
    input[type="file"].form-control::file-selector-button { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); color:var(--rl-white); border:none; padding:.5rem 1rem; border-radius:6px; font-weight:600; margin-right:1rem; cursor:pointer; transition:all .2s ease; }
    input[type="file"].form-control::file-selector-button:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-1px); }
    .btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; padding:.875rem 2.5rem; border-radius:50rem; font-size:1rem; transition:all .2s ease; box-shadow:0 4px 16px rgba(0,78,152,.25); }
    .btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-2px); box-shadow:0 6px 24px rgba(0,78,152,.35); color:var(--rl-white); }
    .btn-primary:active { transform:translateY(0); }
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
    <h1 class="rl-page-title"><i class="bi bi-door-open"></i> Create Room</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>
  <?php if (!is_null($remaining_room_slots)): ?>
    <div class="mb-3">
      <span class="rl-slots-badge"><i class="bi bi-box-seam"></i> Remaining Room Slots: <?php echo (int)$remaining_room_slots; ?></span>
    </div>
  <?php endif; ?>
  <?php /* Flash is shown via global SweetAlert2 in navbar; removed Bootstrap alert markup */ ?>
  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-info-circle"></i> Room Details</h2></div>
    <div class="rl-form-body">
      <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="mb-3">
          <label class="form-label">Title</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
            <input name="title" class="form-control" required maxlength="150" placeholder="Cozy single room">
            <div class="invalid-feedback">Please enter a title (max 150 characters).</div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" rows="3" class="form-control" placeholder="Describe the room, amenities, nearby places..."></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Province</label>
            <select name="province_id" id="province" class="form-select" required data-current=""></select>
            <div class="invalid-feedback">Please select a province.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">District</label>
            <select name="district_id" id="district" class="form-select" required data-current=""></select>
            <div class="invalid-feedback">Please select a district.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">City</label>
            <select name="city_id" id="city" class="form-select" required data-current=""></select>
            <div class="invalid-feedback">Please select a city.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Postal Code</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-mailbox"></i></span>
              <input name="postal_code" class="form-control" required maxlength="10" placeholder="e.g. 10115">
              <div class="invalid-feedback">Please provide a postal code.</div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input name="address" class="form-control" maxlength="255" placeholder="Street, number, etc.">
          </div>
          <div class="col-12">
            <label class="form-label">Google Map Link (optional)</label>
            <input name="google_map_link" class="form-control" maxlength="255" placeholder="https://maps.google.com/...">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Price per day (LKR)</label>
          <div class="input-group">
            <span class="input-group-text">LKR</span>
            <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required placeholder="0.00">
            <div class="invalid-feedback">Please enter a price greater than 0.</div>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col">
            <label class="form-label">Beds</label>
            <input name="beds" type="number" min="1" class="form-control" placeholder="1" value="1">
          </div>
          <div class="col">
            <label class="form-label">Maximum guests</label>
            <input name="maximum_guests" type="number" min="1" class="form-control" placeholder="1" value="1">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Room type</label>
          <select name="room_type" class="form-select">
            <?php
              $types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
              foreach ($types as $t) {
                echo '<option value="'.htmlspecialchars($t).'">'.ucwords(str_replace('_',' ',$t)).'</option>';
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
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_breakfast" placeholder="e.g. 1000.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">Half board</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_half_board" placeholder="e.g. 1500.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">Full board</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_full_board" placeholder="e.g. 2000.00">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text">All inclusive</span>
                <input type="number" step="0.01" min="0" class="form-control" name="meal_price_all_inclusive" placeholder="e.g. 3000.00">
              </div>
            </div>
          </div>
          <div class="form-text">Leave blank to use global meal plan prices. Only meals up to the selected meal plan capability will be applied.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Primary Image (optional, max 5MB)</label>
          <input type="file" name="image" accept="image/*" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Gallery Images (optional, max 5MB each)</label>
          <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
        </div>
        <button type="submit" class="btn btn-primary">Create Room</button>
      </form>
    </div>
  </div>
 </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="js/room_create.js" defer></script>
</body>
</html>
