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

if (!function_exists('slugify')) {
  function slugify($text) {
    $text = strtolower((string)$text);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim($text, '-');
    if ($text === '' || $text === null) { $text = 'item'; }
    return $text;
  }
}

if (!function_exists('create_webp_copy')) {
  function create_webp_copy($sourcePath, $quality = 80) {
    if (!is_file($sourcePath)) { return ''; }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) { return ''; }
    $info = @getimagesize($sourcePath);
    if ($info === false) { return ''; }
    $mime = (string)($info['mime'] ?? '');
    $src = null;
    if (strpos($mime, 'jpeg') !== false) { $src = @imagecreatefromjpeg($sourcePath); }
    elseif (strpos($mime, 'png') !== false) { $src = @imagecreatefrompng($sourcePath); }
    elseif (strpos($mime, 'gif') !== false) { $src = @imagecreatefromgif($sourcePath); }
    elseif (strpos($mime, 'webp') !== false) { $src = @imagecreatefromwebp($sourcePath); }
    if (!$src) { return ''; }
    $target = preg_replace('/\.[a-zA-Z0-9]+$/', '', $sourcePath) . '.webp';
    @imagepalettetotruecolor($src);
    @imagealphablending($src, true);
    @imagesavealpha($src, true);
    $ok = @imagewebp($src, $target, max(0, min(100, (int)$quality)));
    @imagedestroy($src);
    return $ok ? $target : '';
  }
}

if (!function_exists('resize_image_constrain')) {
  function resize_image_constrain($path, $maxW, $maxH) {
    if (!is_file($path)) { return false; }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagescale')) { return false; }
    $info = @getimagesize($path);
    if ($info === false) { return false; }
    $w = (int)($info[0] ?? 0); $h = (int)($info[1] ?? 0);
    if ($w <= 0 || $h <= 0) { return false; }
    if ($w <= $maxW && $h <= $maxH) { return true; }
    $mime = (string)($info['mime'] ?? '');
    $src = null; $writer = null; $ext = '';
    if (strpos($mime, 'jpeg') !== false) { $src = @imagecreatefromjpeg($path); $writer = 'jpeg'; $ext = 'jpg'; }
    elseif (strpos($mime, 'png') !== false) { $src = @imagecreatefrompng($path); $writer = 'png'; $ext = 'png'; }
    elseif (strpos($mime, 'gif') !== false) { $src = @imagecreatefromgif($path); $writer = 'gif'; $ext = 'gif'; }
    elseif (strpos($mime, 'webp') !== false) { $src = @imagecreatefromwebp($path); $writer = 'webp'; $ext = 'webp'; }
    if (!$src) { return false; }
    $ratio = min($maxW / $w, $maxH / $h);
    $newW = max(1, (int)floor($w * $ratio));
    $newH = max(1, (int)floor($h * $ratio));
    $dst = @imagescale($src, $newW, $newH, IMG_BILINEAR_FIXED);
    if (!$dst) { @imagedestroy($src); return false; }
    $ok = false;
    if ($writer === 'jpeg') { $ok = @imagejpeg($dst, $path, 85); }
    elseif ($writer === 'png') { $ok = @imagepng($dst, $path); }
    elseif ($writer === 'gif') { $ok = @imagegif($dst, $path); }
    elseif ($writer === 'webp') { $ok = @imagewebp($dst, $path, 85); }
    @imagedestroy($dst);
    @imagedestroy($src);
    return $ok;
  }
}

// Serve geo data for selects
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

// Guard: require active, paid property package with remaining slots
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  try {
    $q = db()->prepare("SELECT bp.bought_package_id, bp.remaining_properties, bp.end_date
                         FROM bought_packages bp
                         JOIN packages p ON p.package_id = bp.package_id
                         WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                           AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                           AND COALESCE(p.max_properties,0) > 0
                           AND COALESCE(bp.remaining_properties,0) > 0
                         ORDER BY bp.start_date DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute();
    $pkg = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$pkg) {
      redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a property package (paid & active) with remaining slots before creating a property.', 'error');
    }
  } catch (Throwable $e) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a property package before creating a property.', 'error');
  }
}

$remaining_property_slots = null;
try {
  $st = db()->prepare("SELECT bp.remaining_properties
                        FROM bought_packages bp
                        JOIN packages p ON p.package_id = bp.package_id
                        WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                          AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                          AND COALESCE(p.max_properties,0) > 0
                        ORDER BY bp.start_date DESC LIMIT 1");
  $st->bind_param('i', $uid);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  if ($r) { $remaining_property_slots = (int)$r['remaining_properties']; }
} catch (Throwable $e) { /* ignore */ }

$error = '';
$created_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
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
    $google_map_link = trim($_POST['google_map_link'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

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
    } elseif (mb_strlen($google_map_link) > 255) {
      $error = 'Google map link is too long';
    } elseif ($bedrooms < 0 || $bathrooms < 0 || $living_rooms < 0) {
      $error = 'Numeric values must be non-negative';
    } elseif (!is_null($sqft) && $sqft < 0) {
      $error = 'Area must be non-negative';
    } else {
      // Check active paid package with property slots
      $bp = null; $bp_id = 0; $rem_props = 0;
      try {
        $q = db()->prepare("SELECT bp.bought_package_id, bp.remaining_properties, bp.end_date
                             FROM bought_packages bp
                             JOIN packages p ON p.package_id = bp.package_id
                             WHERE bp.user_id=? AND bp.status='active' AND bp.payment_status='paid'
                               AND (bp.end_date IS NULL OR bp.end_date>=NOW())
                               AND COALESCE(p.max_properties,0) > 0
                             ORDER BY bp.start_date DESC LIMIT 1");
        $q->bind_param('i', $uid);
        $q->execute();
        $bp = $q->get_result()->fetch_assoc();
        $q->close();
        if ($bp) { $bp_id = (int)$bp['bought_package_id']; $rem_props = (int)$bp['remaining_properties']; }
      } catch (Throwable $e) { /* ignore */ }
      if ($bp_id <= 0) {
        redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Please buy a property package and complete payment before adding a property.', 'error');
      }
      if ($rem_props <= 0) {
        redirect_with_message($GLOBALS['base_url'] . '/owner/package/buy_advertising_packages.php', 'Your package does not have remaining property slots.', 'error');
      }

      $property_code = 'TEMP-' . bin2hex(random_bytes(4));
      $stmt = db()->prepare('INSERT INTO properties (owner_id, property_code, title, description, price_per_month, bedrooms, bathrooms, living_rooms, garden, gym, pool, sqft, kitchen, parking, water_supply, electricity_supply, property_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $has_kitchen = isset($_POST['has_kitchen']) ? 1 : 0;
      $has_parking = isset($_POST['has_parking']) ? 1 : 0;
      $has_water_supply = isset($_POST['has_water_supply']) ? 1 : 0;
      $has_electricity_supply = isset($_POST['has_electricity_supply']) ? 1 : 0;
      $stmt->bind_param(
        'isssdiiiiiidiiiiss',
        $uid,
        $property_code,
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
        $pending_status
      );
      $pending_status = 'pending';
      if ($stmt->execute()) {
        $new_id = db()->insert_id;
        $stmt->close();
        // Friendly code
        try {
          $final_code = 'PROP-' . str_pad((string)$new_id, 6, '0', STR_PAD_LEFT);
          $upc = db()->prepare('UPDATE properties SET property_code=? WHERE property_id=?');
          $upc->bind_param('si', $final_code, $new_id);
          $upc->execute();
          $upc->close();
        } catch (Throwable $e) { /* ignore */ }
        // Location row
        $loc = db()->prepare('INSERT INTO property_locations (property_id, province_id, district_id, city_id, address, google_map_link, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $gmap = ($google_map_link === '' ? null : $google_map_link);
        $loc->bind_param('iiiisss', $new_id, $province_id, $district_id, $city_id, $address, $gmap, $postal_code);
        $loc->execute();
        $loc->close();
        // Track uploaded images in this same request
        $uploaded_primary = false;
        $uploaded_gallery_count = 0;

        // Handle primary image upload (optional)
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
            $fname = 'prop_' . $new_id . '_' . slugify($title) . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
              resize_image_constrain($dest, 1600, 1200);
              $thumb = $dir . '/prop_' . $new_id . '_' . slugify($title) . '_' . time() . '_thumb.' . $ext;
              @copy($dest, $thumb);
              resize_image_constrain($thumb, 480, 360);
              $relFull = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . basename($dest);
              $thumbWebp = create_webp_copy($thumb) ?: '';
              $relThumb = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . basename($thumbWebp ?: $thumb);
              try { $up = db()->prepare('UPDATE properties SET image=? WHERE property_id=?'); $up->bind_param('si', $relThumb, $new_id); $up->execute(); $up->close(); } catch (Throwable $e) {}
              try { $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 1)'); $pi->bind_param('is', $new_id, $relFull); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
              $uploaded_primary = true;
            }
          }
        }
        // Handle gallery images (optional)
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
            $fname = 'prop_' . $new_id . '_' . slugify($title) . '_' . ($i+1) . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $fname;
            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
              resize_image_constrain($dest, 1600, 1200);
              $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
              try { $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)'); $pi->bind_param('is', $new_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
              $uploaded_gallery_count++;
            }
          }
        }
        // Deduct one property slot from the purchased package
        try {
          if (!empty($bp_id) && $bp_id > 0) {
            $dec = db()->prepare('UPDATE bought_packages SET remaining_properties = remaining_properties - 1 WHERE bought_package_id = ? AND remaining_properties > 0');
            $dec->bind_param('i', $bp_id);
            $dec->execute();
            $dec->close();
          }
        } catch (Throwable $e) { /* ignore deduction failure */ }

        // Reflect new remaining slots in the UI without re-query
        if ($remaining_property_slots !== null && $remaining_property_slots > 0) {
          $remaining_property_slots = $remaining_property_slots - 1;
        }

        $created_id = (int)$new_id;
        $msg = 'Property created successfully.';
        redirect_with_message($GLOBALS['base_url'] . '/owner/property/property_create.php', $msg, 'success');
        exit;
      } else {
        $error = 'Failed to create property';
        $stmt->close();
      }
    }
  }
}

if (empty($flash)) {
  [$flash, $flash_type] = get_flash();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       PROPERTY CREATE FORM CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
    }
    
    /* Page Header */
    .rl-page-header {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border-radius: var(--rl-radius-lg);
      padding: 1.5rem 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--rl-shadow-md);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 1.875rem);
      font-weight: 800;
      color: var(--rl-white);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-btn-back {
      background: var(--rl-white);
      border: none;
      color: var(--rl-primary);
      font-weight: 600;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-btn-back:hover {
      background: var(--rl-light-bg);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      color: var(--rl-primary);
    }
    
    /* Badge */
    .rl-slots-badge {
      background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8533 100%);
      color: var(--rl-white);
      padding: 0.5rem 1.25rem;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.9375rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: var(--rl-shadow-sm);
    }
    
    /* Form Card */
    .rl-form-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-md);
      border: 2px solid var(--rl-border);
      overflow: hidden;
    }
    
    .rl-form-header {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      padding: 1.25rem 1.5rem;
      border-bottom: 2px solid var(--rl-border);
    }
    
    .rl-form-header-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .rl-form-body {
      padding: 2rem 1.5rem;
    }
    
    /* Form Labels */
    .form-label {
      font-weight: 600;
      color: var(--rl-text);
      margin-bottom: 0.5rem;
      font-size: 0.9375rem;
    }
    
    /* Form Controls */
    .form-control,
    .form-select {
      border: 2px solid var(--rl-border);
      border-radius: 10px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      color: var(--rl-text);
      background: var(--rl-white);
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .form-control::placeholder {
      color: #a0aec0;
      font-weight: 400;
    }
    
    .form-control:focus,
    .form-select:focus {
      border-color: var(--rl-primary);
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
      outline: none;
      background: var(--rl-white);
    }
    
    .form-control:hover:not(:focus),
    .form-select:hover:not(:focus) {
      border-color: #cbd5e0;
    }
    
    /* Input Groups */
    .input-group-text {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border: none;
      color: var(--rl-white);
      border-radius: 10px 0 0 10px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      min-width: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .input-group .form-control {
      border-left: none;
      border-radius: 0 10px 10px 0;
    }
    
    /* Checkboxes */
    .form-check {
      padding: 0.75rem 1rem;
      background: #f8fafc;
      border-radius: 8px;
      border: 2px solid var(--rl-border);
      transition: all 0.2s ease;
    }
    
    .form-check:hover {
      background: #f1f5f9;
      border-color: var(--rl-accent);
    }
    
    .form-check-input {
      width: 1.25rem;
      height: 1.25rem;
      border: 2px solid var(--rl-border);
      border-radius: 4px;
      margin-top: 0.125rem;
    }
    
    .form-check-input:checked {
      background-color: var(--rl-primary);
      border-color: var(--rl-primary);
    }
    
    .form-check-input:focus {
      border-color: var(--rl-primary);
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
    }
    
    .form-check-label {
      font-weight: 600;
      color: var(--rl-text);
      font-size: 0.9375rem;
      cursor: pointer;
    }
    
    /* Textarea */
    textarea.form-control {
      resize: vertical;
      min-height: 100px;
    }
    
    /* File Inputs */
    input[type="file"].form-control {
      padding: 0.625rem 1rem;
      cursor: pointer;
    }
    
    input[type="file"].form-control::file-selector-button {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 600;
      margin-right: 1rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    input[type="file"].form-control::file-selector-button:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-1px);
    }
    
    /* Submit Button */
    .btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border: none;
      color: var(--rl-white);
      font-weight: 700;
      padding: 0.875rem 2.5rem;
      border-radius: 50rem;
      font-size: 1rem;
      transition: all 0.2s ease;
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.25);
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: var(--rl-white);
    }
    
    .btn-primary:active {
      transform: translateY(0);
    }
    
    /* Invalid Feedback */
    .invalid-feedback {
      color: #ef4444;
      font-weight: 600;
      font-size: 0.875rem;
      margin-top: 0.5rem;
    }
    
    .form-control.is-invalid,
    .form-select.is-invalid {
      border-color: #ef4444;
    }
    
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    /* Responsive */
    @media (max-width: 767px) {
      .rl-page-header {
        padding: 1.25rem 1rem;
        flex-direction: column;
        align-items: flex-start;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
      }
      
      .rl-btn-back {
        width: 100%;
        justify-content: center;
      }
      
      .rl-form-body {
        padding: 1.5rem 1rem;
      }
      
      .form-control,
      .form-select {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }
      
      .btn-primary {
        width: 100%;
        padding: 0.75rem 2rem;
      }
    }
  </style>
</head>
  <body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container rl-container">
    <!-- Page Header -->
    <div class="rl-page-header">
      <h1 class="rl-page-title">
        <i class="bi bi-building-add"></i>
        Create Property
      </h1>
      <a href="../../owner/index.php" class="rl-btn-back">
        <i class="bi bi-speedometer2"></i>
        Dashboard
      </a>
    </div>

    <?php /* Alerts handled via SweetAlert2. Flash is shown globally in navbar. */ ?>

    <!-- Remaining Slots Badge -->
    <?php if (!is_null($remaining_property_slots)): ?>
      <div class="mb-3">
        <span class="rl-slots-badge">
          <i class="bi bi-box-seam"></i>
          Remaining Property Slots: <?php echo (int)$remaining_property_slots; ?>
        </span>
      </div>
    <?php endif; ?>

    <!-- Property Form -->
    <div class="rl-form-card">
      <div class="rl-form-header">
        <h2 class="rl-form-header-title">
          <i class="bi bi-info-circle"></i>
          Property Details
        </h2>
      </div>
      <div class="rl-form-body">
        <div id="formAlert"></div>
        <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-card-text"></i></span>
              <input name="title" class="form-control" required maxlength="255" placeholder="Spacious 3BR house...">
              <div class="invalid-feedback">Please enter a title (max 255 characters).</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control" placeholder="Describe the property, amenities, nearby places..."></textarea>
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
            <label class="form-label">Price per month (LKR)</label>
            <div class="input-group">
              <span class="input-group-text">LKR</span>
              <input name="price_per_month" type="number" min="0" step="0.01" class="form-control" required placeholder="0.00">
              <div class="invalid-feedback">Please enter a valid non-negative price.</div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col">
              <label class="form-label">Bedrooms</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-door-open"></i></span>
                <input name="bedrooms" type="number" min="0" class="form-control" placeholder="0" value="0">
              </div>
            </div>
            <div class="col">
              <label class="form-label">Bathrooms</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-droplet"></i></span>
                <input name="bathrooms" type="number" min="0" class="form-control" placeholder="0" value="0">
              </div>
            </div>
            <div class="col">
              <label class="form-label">Living rooms</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-house"></i></span>
                <input name="living_rooms" type="number" min="0" class="form-control" placeholder="0" value="0">
              </div>
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
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-rulers"></i></span>
              <input class="form-control" type="number" name="sqft" id="sqft" step="0.01" min="0" placeholder="e.g. 1200">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Primary Image (optional, max 5MB)</label>
            <input type="file" name="image" accept="image/*" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Gallery Images (optional, max 5MB each)</label>
            <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple>
          </div>
          <button type="submit" class="btn btn-primary">Create Property</button>
        </form>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (function(){
      try {
        const err = <?= json_encode($error ?? '') ?>;
        if (err) {
          const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true });
          Toast.fire({ icon: 'error', title: String(err) });
        }
      } catch(_) {}
    })();
  </script>
  <script src="js/property_create.js" defer></script>
</body>
</html>
