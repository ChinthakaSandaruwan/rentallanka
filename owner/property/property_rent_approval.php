<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = ___DIR___ . '/../../error/error.log';
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

require_once ___DIR___ . '/../../public/includes/auth_guard.php';
require_role('owner');
require_once ___DIR___ . '/../../config/config.php';

// CSRF
if (empty($_SESSION['csrf_prop_approvals'])) {
  $_SESSION['csrf_prop_approvals'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_prop_approvals'];

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
$err = '';
$ok = '';

// Handle actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $token)) {
    $err = 'Invalid request.';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $rent_id = (int)($_POST['rent_id'] ?? 0);
    if ($rent_id <= 0) {
      $err = 'Bad input.';
    } else {
      // Verify that this rent belongs to a property owned by this owner
      $q = db()->prepare("SELECT pr.rent_id, pr.property_id, pr.customer_id, pr.status, p.title AS property_title
                          FROM property_rents pr JOIN properties p ON p.property_id = pr.property_id
                          WHERE pr.rent_id = ? AND p.owner_id = ? LIMIT 1");
      $q->bind_param('ii', $rent_id, $owner_id);
      $q->execute();
      $row = $q->get_result()->fetch_assoc();
      $q->close();
      if (!$row) {
        $err = 'Rent request not found.';
      } else if ((string)$row['status'] !== 'pending') {
        $err = 'Only pending requests can be processed.';
      } else {
        $cid = (int)$row['customer_id'];
        $ptitle = (string)($row['property_title'] ?? '');
        if ($action === 'approve') {
          $st = db()->prepare("UPDATE property_rents SET status='booked' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Rent request approved.';
            // Notify customer
            try {
              $titleC = 'Congratulations Property Rent Confirmed';
              // Lookup owner's phone number to include in confirmation
              $ownerPhone = '';
              try {
                $qp = db()->prepare('SELECT phone FROM users WHERE user_id = ? LIMIT 1');
                $qp->bind_param('i', $owner_id);
                $qp->execute();
                $rs = $qp->get_result();
                $rw = $rs ? $rs->fetch_assoc() : null;
                $ownerPhone = (string)($rw['phone'] ?? '');
                $qp->close();
              } catch (Throwable $_) {}
              $msgC = 'Your rent request #' . (int)$rent_id . ' for property ' . ($ptitle !== '' ? $ptitle : '') . ' is confirmed.' . ($ownerPhone !== '' ? (' Owner mobile: ' . $ownerPhone . '.') : '');
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $propIdNullable = (int)$row['property_id'];
              $nt->bind_param('isssi', $cid, $titleC, $msgC, $typeC, $propIdNullable);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore */ }
          } else {
            $err = 'Approval failed.';
          }
          $st->close();
        } else if ($action === 'decline') {
          $st = db()->prepare("UPDATE property_rents SET status='cancelled' WHERE rent_id=? AND status='pending'");
          $st->bind_param('i', $rent_id);
          if ($st->execute() && $st->affected_rows > 0) {
            $ok = 'Rent request declined.';
            // Notify customer
            try {
              $titleC = 'We Are Sorry Property Rent Declined';
              $msgC = 'Your rent request #' . (int)$rent_id . ' for property ' . ($ptitle !== '' ? $ptitle : '') . ' was declined by the owner.';
              $typeC = 'system';
              $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
              $propIdNullable = (int)$row['property_id'];
              $nt->bind_param('isssi', $cid, $titleC, $msgC, $typeC, $propIdNullable);
              $nt->execute();
              $nt->close();
            } catch (Throwable $e) { /* ignore */ }
          } else {
            $err = 'Decline failed.';
          }
          $st->close();
        } else {
          $err = 'Unknown action.';
        }
      }
    }
  }
  // POST-Redirect-GET: always redirect after POST to avoid resubmission on refresh
  $msg = $ok ?: $err ?: 'Action completed.';
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/owner/property/property_rent_approval.php', $msg, $typ);
  exit;
}

// List pending property rents for this owner's properties
$rows = [];
$sql = "SELECT pr.rent_id, pr.property_id, pr.customer_id, pr.price_per_month, pr.status, pr.created_at,
               u.name AS customer_name,
               p.title AS property_title
        FROM property_rents pr
        JOIN properties p ON p.property_id = pr.property_id
        JOIN users u ON u.user_id = pr.customer_id
        WHERE p.owner_id = ? AND pr.status = 'pending'
        ORDER BY pr.created_at DESC, pr.rent_id DESC";
$st = db()->prepare($sql);
$st->bind_param('i', $owner_id);
$st->execute();
$rs = $st->get_result();
while ($a = $rs->fetch_assoc()) { $rows[] = $a; }
$st->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Property Rent Approvals</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       PROPERTY RENT APPROVAL PAGE CUSTOM STYLES
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
      --rl-shadow-lg: 0 10px 30px rgba(0,0,0,.15);
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
    
    .rl-page-title i {
      font-size: 1.75rem;
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
      font-size: 0.9375rem;
    }
    
    .rl-btn-back:hover {
      background: var(--rl-light-bg);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      color: var(--rl-primary);
    }
    
    /* Form Cards */
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
    
    .rl-form-header-title i {
      color: var(--rl-accent);
    }
    
    .rl-form-body {
      padding: 1.5rem;
    }
    
    /* Table Styles */
    .table-responsive {
      border-radius: 0;
      margin: 0;
    }
    
    .table {
      margin-bottom: 0;
      font-size: 0.9375rem;
      width: 100%;
      table-layout: auto;
    }
    
    .table thead th {
      background: #f8fafc;
      border-bottom: 2px solid var(--rl-border);
      color: var(--rl-text-secondary);
      font-weight: 700;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 1rem 0.75rem;
      white-space: normal;
      word-break: break-word;
      /* Disable sticky header to avoid width overflow creating horizontal scrollbars */
      position: static;
      top: auto;
      z-index: auto;
    }
    
    .table tbody tr {
      border-bottom: 1px solid var(--rl-border);
      transition: all 0.2s ease;
    }
    
    .table tbody tr:last-child {
      border-bottom: none;
    }
    
    .table tbody tr:hover {
      background: rgba(0, 78, 152, 0.03);
      /* Remove scale to prevent sub-pixel overflow that causes horizontal scrollbars */
      transform: none;
    }
    
    .table tbody td {
      padding: 0.875rem 0.75rem;
      vertical-align: middle;
      font-size: 0.9375rem;
    }
    
    /* Allow actions column to wrap buttons instead of forcing table to overflow */
    td.text-end { text-align: right; white-space: normal; }
    td.text-end { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: .375rem; }
    td.text-end form { display: inline-block; }
    
    /* ID Column */
    .table tbody td:first-child {
      font-weight: 700;
      color: var(--rl-accent);
    }
    
    /* Price Column */
    .table tbody td:nth-child(4) {
      font-weight: 700;
      color: var(--rl-dark);
    }
    
    /* Empty State */
    .text-muted {
      color: var(--rl-text-muted) !important;
      font-weight: 500;
    }
    
    .table tbody td[colspan] {
      padding: 3rem 1.5rem !important;
      text-align: center;
    }
    
    .table tbody td[colspan]::before {
      content: '📋';
      display: block;
      font-size: 3rem;
      margin-bottom: 0.75rem;
      opacity: 0.5;
    }
    
    /* Buttons */
    .btn {
      font-weight: 600;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      transition: all 0.2s ease;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
    }
    
    .btn-sm {
      padding: 0.375rem 0.875rem;
      font-size: 0.8125rem;
    }
    
    .btn-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border: none;
      color: var(--rl-white);
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }
    
    .btn-success:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
      color: var(--rl-white);
    }
    
    .btn-success:active {
      transform: translateY(0);
    }
    
    .btn-outline-danger {
      border: 2px solid #ef4444;
      color: #dc2626;
      background: transparent;
      font-weight: 600;
    }
    
    .btn-outline-danger:hover {
      background: #ef4444;
      border-color: #dc2626;
      color: var(--rl-white);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    .btn-outline-danger:active {
      transform: translateY(0);
    }
    
    .btn i {
      font-size: 1rem;
    }
    
    /* Form Actions */
    form.d-inline {
      display: inline-block;
    }
    
    .text-end form {
      white-space: nowrap;
    }
    
    /* Responsive Table Wrapper */
    @media (max-width: 991px) {
      .table { font-size: 0.875rem; }
      
      .table thead th,
      .table tbody td {
        padding: 0.75rem 0.5rem;
      }
      
      .btn-sm {
        padding: 0.3rem 0.75rem;
        font-size: 0.75rem;
      }
    }
    
    @media (max-width: 767px) {
      .rl-page-header {
        padding: 1.25rem 1rem;
        flex-direction: column;
        align-items: flex-start;
      }
      
      .rl-page-title {
        font-size: 1.5rem;
      }
      
      .rl-page-title i {
        font-size: 1.5rem;
      }
      
      .rl-btn-back {
        width: 100%;
        justify-content: center;
      }
      
      .rl-form-header {
        padding: 1rem;
      }
      
      .rl-form-header-title {
        font-size: 1rem;
      }
      
      .rl-form-body {
        padding: 0;
      }
      
      .table {
        font-size: 0.8125rem;
      }
      
      .table thead th {
        font-size: 0.75rem;
        padding: 0.75rem 0.5rem;
      }
      
      .table tbody td {
        padding: 0.75rem 0.5rem;
      }
      
      /* Stack action buttons on very small screens */
      .text-end {
        text-align: left !important;
      }
      
      .text-end form {
        display: block;
        margin-bottom: 0.25rem;
      }
      
      .text-end form:last-child {
        margin-bottom: 0;
      }
      
      .text-end .btn-sm {
        width: 100%;
        display: flex;
      }
      
      form.ms-1 {
        margin-left: 0 !important;
      }
    }
    
    /* SweetAlert2 Custom Styling */
    .swal2-popup {
      font-family: 'Inter', sans-serif;
      border-radius: var(--rl-radius-lg);
    }
    
    .swal2-confirm {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%) !important;
      border-radius: 8px !important;
      font-weight: 600 !important;
    }
    
    .swal2-cancel {
      border-radius: 8px !important;
      font-weight: 600 !important;
    }
  </style>
</head>
<body>
<?php require_once ___DIR___ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-journal-check"></i> Property Rent Approvals</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php /* Alerts handled globally via SweetAlert2 (navbar). Removed Bootstrap alerts. */ ?>

  <div class="rl-form-card">
    <div class="rl-form-header"><h2 class="rl-form-header-title"><i class="bi bi-list-check"></i> Pending Requests</h2></div>
    <div class="rl-form-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Property</th>
              <th>Customer</th>
              <th>Price/Month</th>
              <th>Requested At</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $it): ?>
              <tr>
                <td>#<?php echo (int)$it['rent_id']; ?></td>
                <td><?php echo htmlspecialchars($it['property_title'] ?? ('Property #' . (int)$it['property_id'])); ?></td>
                <td><?php echo htmlspecialchars(($it['customer_name'] ?? 'User') . ' (#' . (int)$it['customer_id'] . ')'); ?></td>
                <td>LKR <?php echo number_format((float)($it['price_per_month'] ?? 0), 2); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$it['created_at']))); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline rent-approve-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                  </form>
                  <form method="post" class="d-inline ms-1 rent-decline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="rent_id" value="<?php echo (int)$it['rent_id']; ?>">
                    <input type="hidden" name="action" value="decline">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Decline</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No pending requests found.</td></tr>
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
      document.querySelectorAll('form.rent-approve-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Approve rent request?',
            text: 'Approve request #' + rid + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      });
      document.querySelectorAll('form.rent-decline-form').forEach(function(form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          const rid = form.querySelector('input[name="rent_id"]').value;
          const res = await Swal.fire({
            title: 'Decline rent request?',
            text: 'This cannot be undone. Decline request #' + rid + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, decline',
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

