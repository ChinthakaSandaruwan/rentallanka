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

$q = db()->prepare("SELECT property_id FROM properties WHERE property_id=? AND owner_id=? LIMIT 1");
$q->bind_param('ii', $id, $uid);
$q->execute();
$own = $q->get_result()->fetch_assoc();
$q->close();
if (!$own) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property not found', 'error');
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
        $action = $_POST['action'] ?? '';
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
                    $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));
                    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    if ($ext === '') {
                        $mime = is_array($gInfo) && isset($gInfo['mime']) ? $gInfo['mime'] : '';
                        $map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp'
                        ];
                        $ext = $map[$mime] ?? 'jpg';
                    }
                    $fname = 'prop_' . $id . '_' . ($i + 1) . '_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $fname;
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $dest)) {
                        $rel = rtrim($GLOBALS['base_url'] ?? '', '/') . '/uploads/properties/' . $fname;
                        $pi = db()->prepare('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, 0)');
                        if ($pi) { $pi->bind_param('is', $id, $rel); $pi->execute(); $pi->close(); }
                    }
                }
            }
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_image_edit.php?id=' . (int)$id, 'Images uploaded', 'success');
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
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_image_edit.php?id=' . (int)$id, 'Primary image updated', 'success');
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
                    $fname = basename(parse_url($row['image_path'], PHP_URL_PATH) ?? '');
                    if ($fname) {
                        $full = dirname(__DIR__) . '/uploads/properties/' . $fname;
                        if (is_file($full) && strpos(realpath($full) ?: '', realpath(dirname(__DIR__) . '/uploads/properties')) === 0) {
                            @unlink($full);
                        }
                    }
                    if ((int)$row['is_primary'] === 1) {
                        $nx = db()->prepare('SELECT image_path, image_id FROM property_images WHERE property_id=? ORDER BY is_primary DESC, image_id DESC LIMIT 1');
                        $nx->bind_param('i', $id);
                        $nx->execute();
                        $nrow = $nx->get_result()->fetch_assoc();
                        $nx->close();
                        $newPath = $nrow['image_path'] ?? null;
                        $newId = isset($nrow['image_id']) ? (int)$nrow['image_id'] : 0;
                        if ($newPath && $newId) {
                            $clr = db()->prepare('UPDATE property_images SET is_primary=0 WHERE property_id=?');
                            $clr->bind_param('i', $id);
                            $clr->execute();
                            $clr->close();
                            $sp = db()->prepare('UPDATE property_images SET is_primary=1 WHERE property_id=? AND image_id=?');
                            $sp->bind_param('ii', $id, $newId);
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
            redirect_with_message($GLOBALS['base_url'] . '/owner/property_image_edit.php?id=' . (int)$id, 'Image deleted', 'success');
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
  <title>Manage Property Images</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Manage Images</h1>
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
      <div class="card mb-4">
        <div class="card-header">Upload Images</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="upload_images">
            <div class="mb-3">
              <label class="form-label">Images</label>
              <input type="file" name="gallery_images[]" accept="image/*" class="form-control" multiple required>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
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
            <div class="fw-semibold"><?php echo 'PROP-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); ?></div>
          </div>
          <?php
            $pimg = '';
            try {
              $sp = db()->prepare('SELECT image_path FROM property_images WHERE property_id=? ORDER BY is_primary DESC, image_id DESC LIMIT 1');
              $sp->bind_param('i', $id);
              $sp->execute();
              $prow = $sp->get_result()->fetch_assoc();
              $sp->close();
              $pimg = $prow['image_path'] ?? '';
            } catch (Throwable $e) {}
          ?>
          <div class="ratio ratio-16x9 mb-3 bg-light rounded d-flex align-items-center justify-content-center">
            <?php if (!empty($pimg)): ?>
              <img src="<?php echo htmlspecialchars($pimg); ?>" alt="" class="img-fluid rounded">
            <?php else: ?>
              <span class="text-muted">No primary image</span>
            <?php endif; ?>
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
              <div class="col-12 text-muted">No images.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>

