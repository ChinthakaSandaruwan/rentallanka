<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('owner');

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$pid = (int)($_GET['id'] ?? $_POST['property_id'] ?? 0);
$selection_mode = false;
$owned = null;
if ($pid > 0) {
  // verify ownership exists
  try {
    $chk = db()->prepare('SELECT property_id FROM properties WHERE property_id=? AND owner_id=?');
    $chk->bind_param('ii', $pid, $uid);
    $chk->execute();
    $owned = $chk->get_result()->fetch_row();
    $chk->close();
    if (!$owned) {
      $selection_mode = true;
      $flash = 'Property not found';
      $flash_type = 'error';
    }
  } catch (Throwable $e) {
    $selection_mode = true;
    $flash = 'Error loading property';
    $flash_type = 'error';
  }
} else {
  $selection_mode = true;
}

// Load owner properties for selection if needed
$myprops = [];
if ($selection_mode) {
  try {
    $s = db()->prepare('SELECT property_id, property_code, title, image, created_at, price_per_month FROM properties WHERE owner_id=? ORDER BY created_at DESC LIMIT 100');
    $s->bind_param('i', $uid);
    $s->execute();
    $rs = $s->get_result();
    while ($row = $rs->fetch_assoc()) { $myprops[] = $row; }
    $s->close();
  } catch (Throwable $e) { /* ignore */ }
}

if (!$selection_mode && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    redirect_with_message($GLOBALS['base_url'] . '/owner/index.php', 'Invalid request', 'error');
  }

  try {
    // collect image paths
    $imgs = [];
    $qi = db()->prepare('SELECT image_path FROM property_images WHERE property_id=?');
    $qi->bind_param('i', $pid);
    $qi->execute();
    $rs = $qi->get_result();
    while ($r = $rs->fetch_assoc()) { $imgs[] = $r['image_path'] ?? ''; }
    $qi->close();

    $pp = db()->prepare('SELECT image FROM properties WHERE property_id=? AND owner_id=?');
    $pp->bind_param('ii', $pid, $uid);
    $pp->execute();
    $prow = $pp->get_result()->fetch_assoc();
    $pp->close();
    if (!empty($prow['image'])) { $imgs[] = $prow['image']; }

    // delete files safely
    $baseDir = realpath(dirname(__DIR__, 2) . '/uploads/properties') ?: '';
    foreach ($imgs as $p) {
      if (!$p) continue;
      $fname = basename(parse_url($p, PHP_URL_PATH) ?? '');
      if (!$fname) continue;
      $full = dirname(__DIR__, 2) . '/uploads/properties/' . $fname;
      $real = realpath($full) ?: '';
      if ($real && $baseDir && strpos($real, $baseDir) === 0 && is_file($real)) {
        @unlink($real);
      }
    }

    // delete image rows
    $dp = db()->prepare('DELETE FROM property_images WHERE property_id=?');
    $dp->bind_param('i', $pid);
    $dp->execute();
    $dp->close();
  } catch (Throwable $e) { /* ignore cleanup errors */ }

  // delete property
  $del = db()->prepare('DELETE FROM properties WHERE property_id=? AND owner_id=?');
  $del->bind_param('ii', $pid, $uid);
  if ($del->execute() && $del->affected_rows > 0) {
    $del->close();
    redirect_with_message($GLOBALS['base_url'] . '/owner/property/property_delete.php', 'Property deleted', 'success');
  }
  $del->close();
  redirect_with_message($GLOBALS['base_url'] . '/owner/property/property_delete.php', 'Delete failed', 'error');
}

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Property</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Delete Property</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?> alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($flash); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($selection_mode): ?>
    <div class="card">
      <div class="card-header">Delete a Property</div>
      <div class="card-body">
        <?php if (empty($myprops)): ?>
          <div class="text-muted">No properties found.</div>
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
                    <form method="post" class="mt-auto needs-validation" novalidate>
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
                      <button type="submit" class="btn btn-danger w-100">Delete</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-body">
        <div id="formAlert"></div>
        <p class="mb-3">Are you sure you want to delete <strong>PROP-<?php echo str_pad((string)$pid, 6, '0', STR_PAD_LEFT); ?></strong>? This will remove all its images.</p>
        <form method="post" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="property_id" value="<?php echo (int)$pid; ?>">
          <button type="submit" class="btn btn-danger">Delete</button>
          <a href="../index.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="js/property_delete.js" defer></script>
</body>
</html>
