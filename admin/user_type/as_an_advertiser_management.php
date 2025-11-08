<?php
require_once __DIR__ . '/../../config/config.php';
// Admin-only access
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$role = $_SESSION['role'] ?? '';
if (!$loggedIn || $role !== 'admin') {
  redirect_with_message($base_url . '/auth/login.php', 'Admin access required', 'error');
}

// Ensure PHP errors are logged to the project error log
try { @ini_set('log_errors', '1'); @ini_set('error_log', __DIR__ . '/../../error/error.log'); } catch (Throwable $e) {}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';

// Handle actions: approve (change role to owner), mark_read (dismiss request)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request';
  } else {
    $action = $_POST['action'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);
    $rid = (int)($_POST['request_id'] ?? 0);
    if ($uid <= 0 || $rid <= 0) {
      $err = 'Bad input';
    } else {
      try {
        if ($action === 'approve') {
          // Change role to owner if currently customer
          $stmt = db()->prepare('UPDATE users SET role = "owner" WHERE user_id = ? AND role = "customer"');
          $stmt->bind_param('i', $uid);
          if ($stmt->execute() && $stmt->affected_rows > 0) {
            $ok = 'User #' . $uid . ' upgraded to Owner';
          } else {
            $err = 'No change made (already Owner or user not found)';
            @error_log('[as_adv_mgmt] upgrade failed: ' . (string)db()->error . ' uid=' . $uid);
          }
          $stmt->close();
          // Mark the request as approved
          $st2 = db()->prepare('UPDATE advertiser_requests SET status = "approved", reviewed_by = ? WHERE request_id = ?');
          $admin_id = (int)($_SESSION['user']['user_id'] ?? 0);
          $st2->bind_param('ii', $admin_id, $rid);
          if (!$st2->execute()) { @error_log('[as_adv_mgmt] mark approved failed: ' . (string)db()->error . ' rid=' . $rid); }
          $st2->close();

          // Notify the user about approval
          try {
            $title = 'Advertiser Request Approved';
            $msg = 'Your request (ID #' . $rid . ') has been approved. Your account is now Owner.';
            $type = 'system';
            $n = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
            $n->bind_param('isss', $uid, $title, $msg, $type);
            $n->execute();
            $n->close();
          } catch (Throwable $e3) { @error_log('[as_adv_mgmt] notify approve failed: ' . $e3->getMessage()); }
        } elseif ($action === 'mark_read') {
          // Reject request
          $st2 = db()->prepare('UPDATE advertiser_requests SET status = "rejected", reviewed_by = ? WHERE request_id = ?');
          $admin_id = (int)($_SESSION['user']['user_id'] ?? 0);
          $st2->bind_param('ii', $admin_id, $rid);
          if ($st2->execute()) { $ok = 'Request dismissed'; } else { $err = 'Dismiss failed'; @error_log('[as_adv_mgmt] dismiss failed: ' . (string)db()->error . ' rid=' . $rid); }
          $st2->close();

          // Notify the user about rejection
          try {
            $title = 'Advertiser Request Rejected';
            $msg = 'Your request (ID #' . $rid . ') was rejected by admin.';
            $type = 'system';
            $n = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
            $n->bind_param('isss', $uid, $title, $msg, $type);
            $n->execute();
            $n->close();
          } catch (Throwable $e4) { @error_log('[as_adv_mgmt] notify reject failed: ' . $e4->getMessage()); }
        } else {
          $err = 'Unknown action';
        }
      } catch (Throwable $e) {
        $err = 'Operation failed';
        @error_log('[as_adv_mgmt] exception: ' . $e->getMessage());
      }
    }
  }
}

// Fetch pending role-change requests from dedicated table
$rows = [];
try {
  $sql = "SELECT r.request_id, r.user_id, r.status, r.created_at, u.name, u.email, u.phone, u.role
          FROM advertiser_requests r LEFT JOIN users u ON u.user_id = r.user_id
          WHERE r.status = 'pending'
          ORDER BY r.created_at DESC";
  $res = db()->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  }
} catch (Throwable $e) {}

[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>As an Advertiser - Requests</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Advertiser Requests</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
    </div>

    <?php if ($flash): ?><div class="alert <?= $flash_type==='success'?'alert-success':'alert-danger' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Req ID</th>
                <th>User</th>
                <th>Contact</th>
                <th>Current Role</th>
                <th>Status</th>
                <th>Requested</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td>#<?= (int)$r['request_id'] ?></td>
                  <td><?= htmlspecialchars(($r['name'] ?? 'N/A') . ' (#' . (int)$r['user_id'] . ')') ?></td>
                  <td>
                    <div><?= htmlspecialchars($r['email'] ?? '') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($r['phone'] ?? '') ?></div>
                  </td>
                  <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($r['role'] ?? '') ?></span></td>
                  <td><span class="badge bg-warning text-uppercase"><?= htmlspecialchars($r['status'] ?? '') ?></span></td>
                  <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                      <button class="btn btn-sm btn-success" name="action" value="approve" onclick="return confirm('Approve upgrade for this user?');">
                        <i class="bi bi-check2-circle me-1"></i>Approve
                      </button>
                    </form>
                    <form method="post" class="d-inline ms-1">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                      <button class="btn btn-sm btn-outline-secondary" name="action" value="mark_read">
                        <i class="bi bi-x-circle me-1"></i>Dismiss
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center py-4">No pending advertiser requests.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
