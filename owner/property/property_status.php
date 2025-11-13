<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ___DIR___ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = ___DIR___ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once ___DIR___ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once ___DIR___ . '/../../config/config.php';

// CSRF token for this page
if (empty($_SESSION['csrf_prop_status'])) {
  $_SESSION['csrf_prop_status'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_prop_status'];

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($owner_id <= 0) {
  redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$err = '';
$ok = '';

// Handle status change
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $token)) {
    $err = 'Invalid request.';
  } else {
    $pid = (int)($_POST['property_id'] ?? 0);
    $new_status = (string)($_POST['new_status'] ?? '');
    if ($pid <= 0) {
      $err = 'Bad input.';
    } else if (!in_array($new_status, ['available', 'unavailable'], true)) {
      $err = 'Unsupported status change.';
    } else {
      try {
        // Only allow toggling between available and unavailable for properties owned by the user
        $st = db()->prepare("UPDATE properties SET status=? WHERE property_id=? AND owner_id=? AND status IN ('available','unavailable')");
        $st->bind_param('sii', $new_status, $pid, $owner_id);
        if ($st->execute() && $st->affected_rows > 0) {
          $ok = 'Property status updated to ' . $new_status . '.';
        } else {
          $err = 'Update failed. The property might not be in a changeable state.';
        }
        $st->close();
      } catch (Throwable $e) {
        $err = 'Update failed.';
      }
    }
  }
  $msg = $ok ?: $err ?: 'Action completed.';
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/owner/property/property_status.php', $msg, $typ);
  exit;
}

// Fetch owner properties
$rows = [];
try {
  $sql = "SELECT property_id, property_code, title, status, created_at, price_per_month, image
          FROM properties
          WHERE owner_id = ?
          ORDER BY created_at DESC";
  $st = db()->prepare($sql);
  $st->bind_param('i', $owner_id);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
  $st->close();
} catch (Throwable $e) {}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Property Status</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Shared RL design system */
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-radius:12px; --rl-radius-lg:16px; }
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

    .status-badge { text-transform: uppercase; }
    .table thead th { background:#f8fafc; border-bottom:2px solid var(--rl-border); color:var(--rl-text-secondary); font-weight:700; font-size:.875rem; text-transform:uppercase; letter-spacing:.5px; }
    .table tbody tr { border-bottom:1px solid var(--rl-border); }
    .table tbody tr:hover { background:rgba(0,78,152,.02); }

    .btn-success { box-shadow:0 2px 8px rgba(16,185,129,.2); }
    .btn-success:hover { transform:translateY(-1px); }
    .btn-outline-secondary:hover { transform:translateY(-1px); }

    @media (max-width: 767px){ .rl-page-header{ padding:1rem 1rem; flex-direction:column; align-items:flex-start; } .rl-btn-back{ width:100%; justify-content:center; } .rl-form-body{ padding:1rem; } }
  </style>
  </head>
<body>
<?php require_once ___DIR___ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-sliders2-vertical"></i> Property Status</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php /* Flash handled globally via SweetAlert2 in navbar. No Bootstrap alerts here. */ ?>

  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-list-check"></i> Manage Property Status</h2></div>
    <div class="rl-form-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Title</th>
              <th>Status</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $p): ?>
            <?php $status = (string)($p['status'] ?? 'pending');
                  $pid = (int)$p['property_id'];
                  $code = (string)($p['property_code'] ?? ('PROP-' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT)));
                  $canToggle = in_array($status, ['available','unavailable'], true);
                  $next = $status === 'available' ? 'unavailable' : 'available'; ?>
            <tr>
              <td><?php echo htmlspecialchars($code); ?></td>
              <td><?php echo htmlspecialchars((string)($p['title'] ?? '')); ?></td>
              <td>
                <?php if ($status === 'available'): ?>
                  <span class="badge bg-success status-badge">available</span>
                <?php elseif ($status === 'unavailable'): ?>
                  <span class="badge bg-secondary status-badge">unavailable</span>
                <?php elseif ($status === 'pending'): ?>
                  <span class="badge bg-warning text-dark status-badge">pending</span>
                <?php elseif ($status === 'booked'): ?>
                  <span class="badge bg-info text-dark status-badge">booked</span>
                <?php elseif ($status === 'cancelled'): ?>
                  <span class="badge bg-dark status-badge">cancelled</span>
                <?php else: ?>
                  <span class="badge bg-light text-dark status-badge"><?php echo htmlspecialchars($status); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string)$p['created_at']); ?></td>
              <td class="text-end">
                <?php if ($canToggle): ?>
                  <form method="post" class="d-inline prop-status-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="property_id" value="<?php echo $pid; ?>">
                    <input type="hidden" name="new_status" value="<?php echo htmlspecialchars($next); ?>">
                    <?php if ($status === 'available'): ?>
                      <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye-slash me-1"></i>Mark Unavailable</button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-eye me-1"></i>Mark Available</button>
                    <?php endif; ?>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">Status cannot be changed</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No properties found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    try {
      document.querySelectorAll('form.prop-status-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const codeCell = form.closest('tr')?.querySelector('td:first-child');
          const code = codeCell ? codeCell.textContent.trim() : ('ID ' + (form.querySelector('input[name="property_id"]').value || ''));
          const next = form.querySelector('input[name="new_status"]').value;
          const res = await Swal.fire({
            title: 'Change status?',
            text: 'Set ' + code + ' to ' + next + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, change',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
    } catch(_) {}
  })();
</script>
</body>
</html>

