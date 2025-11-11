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
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); }
    .rl-page-header { background:linear-gradient(135deg,var(--rl-primary) 0%,var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:1.25rem 1.75rem; margin-bottom:1.25rem; box-shadow:var(--rl-shadow-md); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .rl-page-title { font-size:clamp(1.25rem,3vw,1.5rem); font-weight:800; color:var(--rl-white); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-btn-back { background:var(--rl-white); border:none; color:var(--rl-primary); font-weight:600; padding:.5rem 1.25rem; border-radius:8px; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .rl-btn-back:hover { background:var(--rl-light-bg); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); color:var(--rl-primary); }

    .rl-form-card { background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-md); border:2px solid var(--rl-border); overflow:hidden; }
    .rl-form-header { background:linear-gradient(135deg,#f8fafc 0%, #f1f5f9 100%); padding:1rem 1.25rem; border-bottom:2px solid var(--rl-border); }
    .rl-form-header-title { font-size:1rem; font-weight:700; color:var(--rl-text); margin:0; display:flex; align-items:center; gap:.5rem; }
    .rl-form-body { padding:1.25rem; }

    .prop-card { position:relative; transition:all .3s cubic-bezier(.4,0,.2,1); }
    .prop-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .25s ease; }
    .prop-card:hover { transform:translateY(-6px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent) !important; }
    .prop-card:hover::before { opacity:1; }
    .card-img-top { object-fit:cover; height: 200px; }
    .placeholder-img { background:linear-gradient(135deg,#eef2f7 0%, #e2e8f0 100%); color:#64748b; height:200px; }

    .btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; border-radius:10px; box-shadow:0 4px 16px rgba(0,78,152,.2); }
    .btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-1px); }

    .rl-empty-state { text-align:center; padding:3rem 1.5rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:2.5rem; color:var(--rl-secondary); margin-bottom:.5rem; }

    @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } .card-img-top,.placeholder-img{ height:180px; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-trash3"></i> Delete Property</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>
  <?php /* Flash/messages shown via SweetAlert2 in navbar; removed Bootstrap alerts */ ?>

  <?php if ($selection_mode): ?>
    <div class="rl-form-card">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-card-list"></i> Delete a Property</h2></div>
      <div class="rl-form-body">
        <?php if (empty($myprops)): ?>
          <div class="rl-empty-state">
            <i class="bi bi-house"></i>
            <p class="mb-3">No properties found.</p>
            <a href="property_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Property</a>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($myprops as $p): ?>
              <div class="col-12 col-md-6 col-lg-4">
                <div class="rl-form-card h-100 prop-card">
                  <?php $img = trim((string)($p['image'] ?? '')); ?>
                  <?php if ($img): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="Property image">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center placeholder-img"><span>No image</span></div>
                  <?php endif; ?>
                  <div class="rl-form-body d-flex flex-column">
                    <div class="text-muted small">Code</div>
                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars($p['property_code'] ?? ('PROP-' . str_pad((string)$p['property_id'], 6, '0', STR_PAD_LEFT))); ?></div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($p['title'] ?? ''); ?></h6>
                    <div class="text-muted small mb-3">Created: <?php echo htmlspecialchars($p['created_at'] ?? ''); ?></div>
                    <?php if (isset($p['price_per_month'])): ?>
                      <div class="h6 mb-3">LKR <?php echo number_format((float)$p['price_per_month'], 2); ?>/mo</div>
                    <?php endif; ?>
                    <form method="post" class="mt-auto needs-validation prop-del-form" novalidate>
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
    <div class="rl-form-card">
      <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-exclamation-octagon"></i> Confirm Deletion</h2></div>
      <div class="rl-form-body">
        <div id="formAlert"></div>
        <p class="mb-3">Are you sure you want to delete <strong>PROP-<?php echo str_pad((string)$pid, 6, '0', STR_PAD_LEFT); ?></strong>? This will remove all its images.</p>
        <form method="post" class="needs-validation prop-del-form" novalidate>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.prop-del-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const codeEl = form.closest('.card-body')?.querySelector('.fw-semibold') || null;
          const code = codeEl ? codeEl.textContent.trim() : (form.querySelector('input[name="property_id"]').value || 'this property');
          const res = await Swal.fire({
            title: 'Delete property?',
            text: 'This action cannot be undone. Delete ' + code + '?',
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
<script src="js/property_delete.js" defer></script>
</body>
</html>
