<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../../config/config.php';

$sid = (int)$_SESSION['super_admin_id'];

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $stmt = db()->prepare('UPDATE super_admins SET email = ?, phone = ? WHERE super_admin_id = ?');
        $stmt->bind_param('ssi', $email, $phone, $sid);
        $stmt->execute();
        $stmt->close();
        redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Profile updated');
    } elseif ($action === 'change_password') {
        $pwd = (string)($_POST['new_password'] ?? '');
        $pwd2 = (string)($_POST['confirm_password'] ?? '');
        if ($pwd === '' || $pwd !== $pwd2) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Password mismatch', 'error');
        }
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $stmt = db()->prepare('UPDATE super_admins SET password_hash = ? WHERE super_admin_id = ?');
        $stmt->bind_param('si', $hash, $sid);
        $stmt->execute();
        $stmt->close();
        redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Password changed');
    }
}

// Load current SA data
$stmt = db()->prepare('SELECT username, email, phone, created_at, last_login_at, last_login_ip FROM super_admins WHERE super_admin_id = ? LIMIT 1');
$stmt->bind_param('i', $sid);
$stmt->execute();
$res = $stmt->get_result();
$sa = $res->fetch_assoc();
$stmt->close();

[$flash, $flash_type] = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Super Admin Profile</h3>
            <span class="badge bg-danger">SUPER ADMIN</span>
          </div>
          <?php if ($flash): ?>
            <div class="alert alert-<?= $flash_type === 'error' ? 'danger' : 'success' ?> mb-3"><?= htmlspecialchars($flash) ?></div>
          <?php endif; ?>

          <div class="text-center mb-4">
            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
              <span style="font-weight:600;">SA</span>
            </div>
            <div class="mt-2 text-muted">@<?= htmlspecialchars($sa['username'] ?? '') ?></div>
          </div>

          <ul class="list-group mb-4">
            <li class="list-group-item d-flex justify-content-between"><span>Email</span><span><?= htmlspecialchars($sa['email'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Phone</span><span><?= htmlspecialchars($sa['phone'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Last Login</span><span><?= htmlspecialchars($sa['last_login_at'] ?: '-') ?> <?= $sa['last_login_ip'] ? '(' . htmlspecialchars($sa['last_login_ip']) . ')' : '' ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Created</span><span><?= htmlspecialchars($sa['created_at'] ?? '-') ?></span></li>
          </ul>

          <h5 class="mb-3">Edit Profile</h5>
          <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($sa['email'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($sa['phone'] ?? '') ?>">
            </div>
            <div class="col-12">
              <input type="hidden" name="action" value="update_profile">
              <button class="btn btn-primary" type="submit">Save</button>
            </div>
          </form>

          <h5 class="mt-4 mb-3">Change Password</h5>
          <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <div class="col-12">
              <input type="hidden" name="action" value="change_password">
              <button class="btn btn-warning" type="submit">Change Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
