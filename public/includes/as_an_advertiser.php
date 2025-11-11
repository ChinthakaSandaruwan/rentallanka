<?php
require_once __DIR__ . '/../../config/config.php';

$setLog = function() {
  try { @ini_set('log_errors', '1'); @ini_set('error_log', __DIR__ . '/../../error/error.log'); } catch (Throwable $e) {}
};
$setLog();

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$user = $_SESSION['user'] ?? [];
$role = $_SESSION['role'] ?? '';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';
$hasPending = false;

// Pre-check: show pending state on initial load
try {
  if ($loggedIn && $role === 'customer') {
    $uid = (int)($user['user_id'] ?? 0);
    if ($uid > 0) {
      $stc0 = db()->prepare("SELECT request_id FROM advertiser_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
      $stc0->bind_param('i', $uid);
      $stc0->execute();
      $has0 = $stc0->get_result()->fetch_assoc();
      $stc0->close();
      if ($has0) { $hasPending = true; }
    }
  }
} catch (Throwable $e) { /* ignore */ }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request.';
  } else {
    if (!$loggedIn) {
      $err = 'Please login to continue.';
    } elseif ($role !== 'customer') {
      if ($role === 'owner') {
        $err = 'Your account is already an Owner.';
      } else {
        $err = 'Only Customer accounts can request an upgrade.';
      }
    } else {
      try {
        $uid = (int)($user['user_id'] ?? 0);
        $name = (string)($user['name'] ?? ''); // may be empty depending on session structure
        // Fetch latest name/email if needed
        $stmt = db()->prepare('SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: [];
        $stmt->close();
        $name = $row['name'] ?? $name;
        $email = $row['email'] ?? '';
        $phone = $row['phone'] ?? ($user['phone'] ?? '');

        // Prevent duplicate pending requests
        $stc = db()->prepare("SELECT request_id FROM advertiser_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $stc->bind_param('i', $uid);
        $stc->execute();
        $has = $stc->get_result()->fetch_assoc();
        $stc->close();
        if ($has) {
          $ok = 'You already have a pending request. Our admin team will review it soon.';
        } else {
          $sti = db()->prepare("INSERT INTO advertiser_requests (user_id, status) VALUES (?, 'pending')");
          $sti->bind_param('i', $uid);
          if ($sti->execute()) {
            $ok = 'Your request has been submitted for admin approval.';
            // Notify first active admin about the new request
            try {
              $arid = (int)$sti->insert_id;
              $resAdm = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
              $adminId = 0;
              if ($resAdm && ($rowAdm = $resAdm->fetch_assoc())) { $adminId = (int)$rowAdm['user_id']; }
              if ($adminId > 0) {
                $titleN = 'New Advertiser Request';
                $msgN = 'Customer #' . (int)$uid . ' requested to become an advertiser (Request #' . $arid . ').';
                $typeN = 'system';
                $ns = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
                $ns->bind_param('isss', $adminId, $titleN, $msgN, $typeN);
                $ns->execute();
                $ns->close();
              }
            } catch (Throwable $e2) { @error_log('[as_an_advertiser] admin notify failed: ' . $e2->getMessage()); }
          } else {
            $err = 'Could not submit request. Please try again.';
            @error_log('[as_an_advertiser] insert failed: ' . (string)db()->error);
          }
          $sti->close();
        }
      } catch (Throwable $e) {
        $err = 'Could not submit request. Please try again later.';
        @error_log('[as_an_advertiser] exception: ' . $e->getMessage());
      }
    }
  }
}

// POST-Redirect-GET: redirect with flash so SweetAlert2 shows message via navbar
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $msg = $ok ?: ($err ?: 'Action completed.');
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/as_an_advertiser.php', $msg, $typ);
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Become an Advertiser</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"></head>
<body>
  <?php require_once __DIR__ . '/navbar.php'; ?>
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h4 mb-0"><i class="bi bi-briefcase me-2"></i>Become an Advertiser</h1>
          <a href="<?= $base_url ?>/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house me-1"></i>Home</a>
        </div>

        <?php if (!$loggedIn): ?>
          <p class="text-muted">Please <a href="<?= $base_url ?>/auth/login.php">log in</a> to request an upgrade.</p>
        <?php endif; ?>

        <div class="card shadow-sm">
          <div class="card-body p-4">
            <?php if ($loggedIn && $role === 'customer'): ?>
              <p class="text-muted">Upgrade your Customer account to an Owner account to start posting properties and rooms as an advertiser. Your request will be reviewed by an Admin.</p>
              <ul class="mb-3 small text-muted">
                <li>Admin approval is required.</li>
                <li>We may contact you for verification.</li>
              </ul>
              <?php if ($hasPending): ?>
                <p class="text-muted d-flex align-items-center"><i class="bi bi-hourglass-split me-2"></i><span>Your request has been sent and is pending admin review.</span></p>
              <?php endif; ?>
              <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                <div class="mb-3">
                  <label class="form-label">Confirm your intention</label>
                  <input type="text" class="form-control" value="I want to upgrade to Owner" disabled />
                </div>
                <div class="d-grid">
                  <button type="submit" class="btn btn-primary" <?= $hasPending ? 'disabled' : '' ?>><i class="bi bi-send me-1"></i><?= $hasPending ? 'Request Pending' : 'Send Request' ?></button>
                </div>
              </form>
            <?php elseif ($loggedIn && $role === 'owner'): ?>
              <p class="text-muted">Your account is already an Owner.</p>
            <?php else: ?>
              <p class="mb-0">Log in as a Customer to request an upgrade.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', async (e) => {
          if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); form.classList.add('was-validated'); return; }
          e.preventDefault();
          // SweetAlert2 confirmation before sending request
          try {
            const res = await Swal.fire({
              title: 'Send upgrade request?',
              text: 'We will notify an admin to review your request.',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, send',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          } catch(_) { form.submit(); }
        });
      });
    })();
  </script>
</body>
</html>
