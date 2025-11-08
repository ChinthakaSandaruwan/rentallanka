 <?php
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

 $room_id = (int)($_GET['id'] ?? 0);
 $room = null;
 $images = [];
 $flash = '';
 $flash_type = '';
 $error = '';

 if ($room_id > 0) {
   $stmt = db()->prepare("SELECT r.*, l.location_id, l.province_id, l.district_id, l.city_id, l.address, l.postal_code
                          FROM rooms r
                          LEFT JOIN locations l ON l.room_id = r.room_id
                          WHERE r.room_id = ? AND r.owner_id = ?");
   $stmt->bind_param('ii', $room_id, $uid);
   $stmt->execute();
   $rs = $stmt->get_result();
   $room = $rs->fetch_assoc() ?: null;
   $stmt->close();
   if ($room) {
     $im = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, uploaded_at DESC');
     $im->bind_param('i', $room_id);
     $im->execute();
     $ir = $im->get_result();
     while ($row = $ir->fetch_assoc()) { $images[] = $row; }
     $im->close();
   }
 }

 if ($room && $_SERVER['REQUEST_METHOD'] === 'POST') {
   $token = $_POST['csrf_token'] ?? '';
   if (!hash_equals($_SESSION['csrf_token'], $token)) {
     $error = 'Invalid request';
   } else {
     if (isset($_POST['action'])) {
       $action = $_POST['action'];
       if ($action === 'set_primary') {
         $image_id = (int)($_POST['image_id'] ?? 0);
         $chk = db()->prepare('SELECT image_id FROM room_images WHERE image_id=? AND room_id=?');
         $chk->bind_param('ii', $image_id, $room_id);
         $chk->execute();
         $has = $chk->get_result()->fetch_assoc();
         $chk->close();
         if ($has) {
           db()->begin_transaction();
           try {
             $off = db()->prepare('UPDATE room_images SET is_primary=0 WHERE room_id=?');
             $off->bind_param('i', $room_id);
             $off->execute();
             $off->close();
             $on = db()->prepare('UPDATE room_images SET is_primary=1 WHERE image_id=?');
             $on->bind_param('i', $image_id);
             $on->execute();
             $on->close();
             db()->commit();
             $flash = 'Primary image updated.';
             $flash_type = 'success';
           } catch (Throwable $e) {
             db()->rollback();
             $error = 'Failed to set primary image';
           }
         } else {
           $error = 'Image not found';
         }
       } elseif ($action === 'delete_image') {
         $image_id = (int)($_POST['image_id'] ?? 0);
         $g = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE image_id=? AND room_id=?');
         $g->bind_param('ii', $image_id, $room_id);
         $g->execute();
         $img = $g->get_result()->fetch_assoc();
         $g->close();
         if ($img) {
           $path = $img['image_path'];
           if ($path) {
             $prefix = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/';
             if (strpos($path, $prefix) === 0) {
               $fname = substr($path, strlen($prefix));
               $full = dirname(__DIR__, 2) . '/uploads/rooms/' . $fname;
               if (is_file($full)) { @unlink($full); }
             }
           }
           $del = db()->prepare('DELETE FROM room_images WHERE image_id=? AND room_id=?');
           $del->bind_param('ii', $image_id, $room_id);
           $del->execute();
           $del->close();
           if ((int)$img['is_primary'] === 1) {
             $next = db()->prepare('SELECT image_id FROM room_images WHERE room_id=? ORDER BY uploaded_at DESC LIMIT 1');
             $next->bind_param('i', $room_id);
             $next->execute();
             $nr = $next->get_result()->fetch_assoc();
             $next->close();
             if ($nr) {
               $sp = db()->prepare('UPDATE room_images SET is_primary=1 WHERE image_id=?');
               $sp->bind_param('i', $nr['image_id']);
               $sp->execute();
               $sp->close();
             }
           }
           $flash = 'Image deleted.';
           $flash_type = 'success';
         } else {
           $error = 'Image not found';
         }
       }
     } else {
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
       } elseif ($beds < 1 || $maximum_guests < 1) {
         $error = 'Beds and maximum guests must be at least 1';
       } else {
         $u = db()->prepare('UPDATE rooms SET title=?, description=?, room_type=?, beds=?, maximum_guests=?, price_per_day=?, updated_at=NOW() WHERE room_id=? AND owner_id=?');
         $u->bind_param('sssiiiii', $title, $description, $room_type, $beds, $maximum_guests, $price_per_day, $room_id, $uid);
         if ($u->execute()) {
           $u->close();
           if (!empty($room['location_id'])) {
             $ul = db()->prepare('UPDATE locations SET province_id=?, district_id=?, city_id=?, address=?, postal_code=? WHERE location_id=?');
             $ul->bind_param('iiissi', $province_id, $district_id, $city_id, $address, $postal_code, $room['location_id']);
             $ul->execute();
             $ul->close();
           } else {
             $il = db()->prepare('INSERT INTO locations (room_id, province_id, district_id, city_id, address, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
             $il->bind_param('iiiiss', $room_id, $province_id, $district_id, $city_id, $address, $postal_code);
             $il->execute();
             $il->close();
           }
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
               $fname = 'room_' . $room_id . '_' . time() . '.' . $ext;
               $dest = $dir . '/' . $fname;
               if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                 $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                 db()->begin_transaction();
                 try {
                   $off = db()->prepare('UPDATE room_images SET is_primary=0 WHERE room_id=?');
                   $off->bind_param('i', $room_id);
                   $off->execute();
                   $off->close();
                   $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 1)');
                   $pi->bind_param('is', $room_id, $rel);
                   $pi->execute();
                   $pi->close();
                   db()->commit();
                 } catch (Throwable $e) { db()->rollback(); }
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
               $fname = 'room_' . $room_id . '_' . ($i+1) . '_' . time() . '.' . $ext;
               $dest = $dir . '/' . $fname;
               if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                 $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/rooms/' . $fname;
                 try { $pi = db()->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, 0)'); $pi->bind_param('is', $room_id, $rel); $pi->execute(); $pi->close(); } catch (Throwable $e) {}
               }
             }
           }
           $flash = 'Room updated successfully.';
           $flash_type = 'success';
         } else {
           $error = 'Failed to update room';
           $u->close();
         }
       }
     }
   }
   $images = [];
   $im = db()->prepare('SELECT image_id, image_path, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, uploaded_at DESC');
   $im->bind_param('i', $room_id);
   $im->execute();
   $ir = $im->get_result();
   while ($row = $ir->fetch_assoc()) { $images[] = $row; }
   $im->close();
 }

 [$flash, $flash_type] = [$flash ?: (get_flash()[0] ?? ''), $flash_type ?: (get_flash()[1] ?? '')];
 ?>
 <!doctype html>
 <html lang="en">
 <head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Update Room</title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 </head>
 <body>
 <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
 <div class="container py-4">
   <div class="d-flex align-items-center justify-content-between mb-3">
     <h1 class="h3 mb-0">Update Room</h1>
     <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
   </div>
   <?php if (!empty($error)): ?>
     <div class="alert alert-danger alert-dismissible fade show" role="alert">
       <?php echo htmlspecialchars($error); ?>
       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>
   <?php endif; ?>
   <?php if (!empty($flash)): ?>
     <?php $map = ['error'=>'danger','danger'=>'danger','success'=>'success','warning'=>'warning','info'=>'info']; $type = $map[$flash_type ?? 'info'] ?? 'info'; ?>
     <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
       <?php echo htmlspecialchars($flash); ?>
       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>
   <?php endif; ?>

   <?php if (!$room): ?>
     <?php
       $cards = [];
       $q = db()->prepare('SELECT r.room_id, r.title, r.price_per_day, (SELECT image_path FROM room_images WHERE room_id=r.room_id AND is_primary=1 ORDER BY uploaded_at DESC LIMIT 1) AS image_path FROM rooms r WHERE r.owner_id=? ORDER BY r.created_at DESC');
       $q->bind_param('i', $uid);
       $q->execute();
       $rr = $q->get_result();
       while ($row = $rr->fetch_assoc()) { $cards[] = $row; }
       $q->close();
     ?>
     <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
       <?php foreach ($cards as $c): ?>
         <div class="col">
           <div class="card h-100">
             <?php if (!empty($c['image_path'])): ?>
               <img src="<?php echo htmlspecialchars($c['image_path']); ?>" class="card-img-top" alt="Room image">
             <?php else: ?>
               <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                 <span class="text-muted">No image</span>
               </div>
             <?php endif; ?>
             <div class="card-body d-flex flex-column">
               <h5 class="card-title mb-1"><?php echo htmlspecialchars($c['title'] ?: 'Untitled'); ?></h5>
               <div class="text-muted mb-3">LKR <?php echo number_format((float)$c['price_per_day'], 2); ?> / day</div>
               <div class="mt-auto">
                 <a href="?id=<?php echo (int)$c['room_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
               </div>
             </div>
           </div>
         </div>
       <?php endforeach; ?>
       <?php if (empty($cards)): ?>
         <div class="col">
           <div class="alert alert-info">You have no rooms yet. Create one first.</div>
         </div>
       <?php endif; ?>
     </div>
   <?php else: ?>
     <div class="row g-3">
       <div class="col-lg-8">
         <div class="card">
           <div class="card-header">Update Details</div>
           <div class="card-body">
             <div id="formAlert"></div>
             <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
               <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
               <div class="mb-3">
                 <label class="form-label">Title</label>
                 <div class="input-group">
                   <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                   <input name="title" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($room['title'] ?? ''); ?>">
                 </div>
               </div>
               <div class="mb-3">
                 <label class="form-label">Description</label>
                 <textarea name="description" rows="3" class="form-control"><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
               </div>
               <div class="row g-3 mb-3">
                 <div class="col-md-6">
                   <label class="form-label">Province</label>
                   <select name="province_id" id="province" class="form-select" required data-current="<?php echo (int)($room['province_id'] ?? 0); ?>"></select>
                 </div>
                 <div class="col-md-6">
                   <label class="form-label">District</label>
                   <select name="district_id" id="district" class="form-select" required data-current="<?php echo (int)($room['district_id'] ?? 0); ?>"></select>
                 </div>
                 <div class="col-md-6">
                   <label class="form-label">City</label>
                   <select name="city_id" id="city" class="form-select" required data-current="<?php echo (int)($room['city_id'] ?? 0); ?>"></select>
                 </div>
                 <div class="col-md-6">
                   <label class="form-label">Postal Code</label>
                   <input name="postal_code" class="form-control" required maxlength="10" value="<?php echo htmlspecialchars($room['postal_code'] ?? ''); ?>">
                 </div>
                 <div class="col-12">
                   <label class="form-label">Address</label>
                   <input name="address" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($room['address'] ?? ''); ?>">
                 </div>
               </div>
               <div class="mb-3">
                 <label class="form-label">Price per day (LKR)</label>
                 <div class="input-group">
                   <span class="input-group-text">LKR</span>
                   <input name="price_per_day" type="number" min="0" step="0.01" class="form-control" required value="<?php echo htmlspecialchars((string)$room['price_per_day']); ?>">
                 </div>
               </div>
               <div class="row g-3 mb-3">
                 <div class="col">
                   <label class="form-label">Beds</label>
                   <input name="beds" type="number" min="1" class="form-control" value="<?php echo (int)($room['beds'] ?? 1); ?>">
                 </div>
                 <div class="col">
                   <label class="form-label">Maximum guests</label>
                   <input name="maximum_guests" type="number" min="1" class="form-control" value="<?php echo (int)($room['maximum_guests'] ?? 1); ?>">
                 </div>
               </div>
               <div class="mb-3">
                 <label class="form-label">Room type</label>
                 <select name="room_type" class="form-select">
                   <?php $types = ['single','double','twin','suite','deluxe','family','studio','dorm','apartment','villa','penthouse','shared','conference','meeting','other'];
                     foreach ($types as $t) { $sel = ($room['room_type'] ?? '') === $t ? ' selected' : ''; echo '<option value="'.htmlspecialchars($t).'"'.$sel.'>'.ucwords(str_replace('_',' ',$t))."</option>"; }
                   ?>
                 </select>
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
       <div class="col-lg-4">
         <div class="card h-100">
           <div class="card-header d-flex justify-content-between align-items-center">
             <span>Images</span>
           </div>
           <div class="card-body">
             <?php if (empty($images)): ?>
               <div class="text-muted">No images yet.</div>
             <?php endif; ?>
             <div class="row g-3">
               <?php foreach ($images as $img): ?>
                 <div class="col-12">
                   <div class="border rounded p-2 d-flex gap-2 align-items-start">
                     <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Image" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                     <div class="flex-grow-1">
                       <?php if ((int)$img['is_primary'] === 1): ?>
                         <span class="badge bg-success mb-2">Primary</span>
                       <?php endif; ?>
                       <div class="d-flex gap-2">
                         <?php if ((int)$img['is_primary'] !== 1): ?>
                           <form method="post" class="d-inline">
                             <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                             <input type="hidden" name="action" value="set_primary">
                             <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                             <button class="btn btn-outline-primary btn-sm" type="submit">Set as Primary</button>
                           </form>
                         <?php endif; ?>
                         <form method="post" class="d-inline" onsubmit="return confirm('Delete this image?');">
                           <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                           <input type="hidden" name="action" value="delete_image">
                           <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                           <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                         </form>
                       </div>
                     </div>
                   </div>
                 </div>
               <?php endforeach; ?>
             </div>
           </div>
         </div>
       </div>
     </div>
   <?php endif; ?>
 </div>
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="js/room_update.js" defer></script>
 </body>
 </html>
