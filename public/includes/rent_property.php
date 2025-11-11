<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
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
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
$pid = (int)($_GET['id'] ?? $_POST['property_id'] ?? 0);
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') || isset($_GET['ajax']) || isset($_POST['ajax']);

if ($pid <= 0) {
  http_response_code(302);
  header('Location: ' . rtrim($base_url, '/') . '/index.php');
  exit;
}

// Require login
if ($uid <= 0) {
  if ($isAjax) {
    if (function_exists('app_log')) { app_log('[rent_property] unauthenticated isAjax uid=0 pid='.(int)$pid); }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Please sign in to rent a property']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/auth/login.php', 'Please sign in to rent a property', 'info');
}

// Fetch property and ensure available
$stmt = db()->prepare('SELECT property_id, owner_id, title, price_per_month, status FROM properties WHERE property_id = ? LIMIT 1');
$stmt->bind_param('i', $pid);
$stmt->execute();
$prop = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (function_exists('app_log')) { app_log('[rent_property] GET pid='.(int)$pid.' uid='.(int)$uid.' found_prop='.(isset($prop['property_id'])?1:0)); }
if (!$prop) {
  if ($isAjax) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/property.php?id='.(int)$pid, 'Property not found.', 'error');
}
// Prevent owners from renting their own property
if ((int)($prop['owner_id'] ?? 0) === $uid) {
  if (function_exists('app_log')) { app_log('[rent_property] blocked: owner trying to rent own property pid='.(int)$pid.' uid='.(int)$uid); }
  if ($isAjax) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You cannot rent your own property.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/property.php?id='.(int)$pid, 'You cannot rent your own property.', 'error');
}
if (strtolower((string)($prop['status'] ?? '')) !== 'available') {
  if (function_exists('app_log')) { app_log('[rent_property] blocked: property not available pid='.(int)$pid.' status='.(string)($prop['status'] ?? '')); }
  if ($isAjax) {
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'This property is not available for rent.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/property.php?id='.(int)$pid, 'This property is not available for rent.', 'error');
}

// CSRF token
if (empty($_SESSION['csrf_property_rent'])) {
  $_SESSION['csrf_property_rent'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_property_rent'];

$errors = [];
$success = false;
$rentId = 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $token)) {
    $errors[] = 'Invalid request.';
    if (function_exists('app_log')) { app_log('[rent_property] CSRF mismatch uid='.(int)$uid.' pid='.(int)$pid); }
  }

  if (!$errors) {
    $ppm = (float)($prop['price_per_month'] ?? 0);
    db()->begin_transaction();
    try {
      // Insert property rent as pending
      $ins = db()->prepare("INSERT INTO property_rents (property_id, customer_id, price_per_month, status) VALUES (?,?,?,'pending')");
      if (!$ins) { throw new Exception('Prepare failed: ' . (string)db()->error); }
      $ins->bind_param('iid', $pid, $uid, $ppm);
      $ok1 = $ins->execute();
      if (!$ok1) { $errMsg = (string)($ins->error ?? ''); $ins->close(); throw new Exception('Insert failed: ' . $errMsg); }
      $rentId = (int)db()->insert_id;
      $ins->close();
      if (function_exists('app_log')) { app_log('[rent_property] inserted rent_id='. (int)$rentId .' pid='.(int)$pid.' uid='.(int)$uid); }

      // Notify owner (best-effort)
      try {
        $ownerId = (int)($prop['owner_id'] ?? 0);
        if ($ownerId > 0) {
          $titleN = 'Property Rent Pending Request Came, Please Get a Action';
          $customerPhone = '';
          try {
            $q2 = db()->prepare('SELECT phone FROM users WHERE user_id = ? LIMIT 1');
            $q2->bind_param('i', $uid);
            $q2->execute();
            $r2 = $q2->get_result();
            $row2 = $r2 ? $r2->fetch_assoc() : null;
            $customerPhone = (string)($row2['phone'] ?? '');
            $q2->close();
          } catch (Throwable $_) {}
          $msgN = 'A new rent request #' . (int)$rentId . ' for property ' . (string)($prop['title'] ?? ('#'.$pid)) . ' is pending your approval.' . ($customerPhone !== '' ? (' Customer mobile: ' . $customerPhone . '.') : '');
          $typeN = 'system';
          $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
          $propIdNullable = $pid; // store property_id to link notification
          $nt->bind_param('isssi', $ownerId, $titleN, $msgN, $typeN, $propIdNullable);
          $nt->execute();
          $nt->close();
        }
      } catch (Throwable $eN) { /* ignore */ }

      // Notify customer (best-effort)
      try {
        $titleC = 'Property Rent Pending Please Wait';
        $msgC = 'Your rent request #' . (int)$rentId . ' for property ' . (string)($prop['title'] ?? ('#'.$pid)) . ' is pending owner approval.';
        $typeC = 'system';
        $nt2 = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, ?)');
        $propIdNullable = $pid;
        $nt2->bind_param('isssi', $uid, $titleC, $msgC, $typeC, $propIdNullable);
        $nt2->execute();
        $nt2->close();
      } catch (Throwable $eC) { /* ignore */ }

      db()->commit();
      $success = true;
    } catch (Throwable $e) {
      db()->rollback();
      $errors[] = 'Rent request failed. ' . $e->getMessage();
      if (function_exists('app_log')) { app_log('[rent_property] error: '.$e->getMessage()); }
    }
  }

  if ($isAjax) {
    header('Content-Type: application/json');
    if ($success) {
      echo json_encode(['status' => 'success', 'message' => 'Rent request pending', 'rent_id' => (int)$rentId]);
    } else {
      if (function_exists('app_log')) { app_log('[rent_property] ajax_error uid='.(int)$uid.' pid='.(int)$pid.' msg='.(string)($errors ? $errors[0] : 'unknown')); }
      echo json_encode(['status' => 'error', 'message' => ($errors ? implode("\n", $errors) : 'Rent request failed')]);
    }
    exit;
  }
  // Non-AJAX POST: redirect to My Rentals with a flash message
  $msg = $success ? ('Request #' . (int)$rentId . ' submitted and pending approval.') : (($errors ? implode(" \n", $errors) : 'Rent request failed'));
  $typ = $success ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/my_rentals.php', $msg, $typ);
}

// GET render minimal form for modal
if ($isAjax) {
  header('Content-Type: text/html; charset=utf-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-0 m-0">
  <div class="container p-3">
    <h2 class="h5 mb-3">Rent Property</h2>
    <div class="mb-3">
      <div class="fw-semibold"><?php echo htmlspecialchars($prop['title'] ?? 'Property'); ?></div>
      <div class="text-muted small">Price per month</div>
      <div class="display-6 fs-4">LKR <?php echo number_format((float)($prop['price_per_month'] ?? 0), 2); ?></div>
    </div>
    <form method="post" id="formPropertyRent" action="<?php echo rtrim($base_url, '/'); ?>/public/includes/rent_property.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="property_id" value="<?php echo (int)$pid; ?>">
      <p class="text-muted">This request will be sent to the owner for approval.</p>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Submit Request</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      (function(){
        const form = document.getElementById('formPropertyRent');
        if (!form) return;
        form.addEventListener('submit', async function(ev){
          ev.preventDefault();
          const fd = new FormData(form);
          fd.append('ajax','1');
          try {
            const res = await fetch('<?php echo rtrim($base_url, '/'); ?>/public/includes/rent_property.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (data.status === 'success') {
              // Redirect to My Rentals where cancel is available for pending and booked states
              const rid = data.rent_id ? ('#' + data.rent_id) : '';
              const url = '<?php echo rtrim($base_url, '/'); ?>/public/includes/my_rentals.php?flash=' + encodeURIComponent('Request ' + rid + ' submitted and pending approval.') + '&type=success';
              window.location.href = url;
            } else {
              if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Error', text: String(data.message || 'Failed'), confirmButtonText: 'OK' });
              }
            }
          } catch (e) {
            if (window.Swal) {
              Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.', confirmButtonText: 'OK' });
            }
          }
        });

        // Ensure Cancel button works even without Bootstrap JS/modal context
        const cancelBtn = document.querySelector('[data-bs-dismiss="modal"]');
        if (cancelBtn) {
          cancelBtn.addEventListener('click', function(e){
            e.preventDefault();
            try {
              const modalEl = cancelBtn.closest('.modal');
              if (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const inst = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                inst.hide();
                return;
              }
            } catch (_) { /* ignore and fallback */ }
            // Fallback: navigate to My Rentals (as requested)
            window.location.href = '<?php echo rtrim($base_url, '/'); ?>/public/includes/my_rentals.php?flash=Action+completed.&type=success';
          });
        }
      })();
    </script>
  </div>
</body>
</html>

