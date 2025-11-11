<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

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
  <style>
    .status-badge { text-transform: uppercase; }
  </style>
  </head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-sliders2-vertical me-2"></i>Property Status</h1>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
  </div>

  <?php /* Flash handled globally via SweetAlert2 in navbar. No Bootstrap alerts here. */ ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
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

