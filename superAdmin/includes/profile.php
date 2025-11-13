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
require_super_admin();
require_once ___DIR___ . '/../../config/config.php';

$sid = (int)$_SESSION['super_admin_id'];

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Enter a valid name (max 100 chars)', 'error');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Enter a valid email', 'error');
        }
        if ($phone !== '' && !preg_match('/^0[7][01245678][0-9]{7}$/', preg_replace('/\D+/', '', $phone))) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Enter a valid Sri Lanka mobile (07XXXXXXXX) or leave blank', 'error');
        }
        // Enforce email uniqueness among super_admins (excluding self)
        $ck = db()->prepare('SELECT super_admin_id FROM super_admins WHERE email = ? AND super_admin_id <> ? LIMIT 1');
        if ($ck) {
            $ck->bind_param('si', $email, $sid);
            $ck->execute();
            $dup = $ck->get_result()->fetch_assoc();
            $ck->close();
            if ($dup) {
                redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Email is already used by another super admin', 'error');
            }
        }
        $stmt = db()->prepare('UPDATE super_admins SET name = ?, email = ?, phone = ? WHERE super_admin_id = ?');
        $stmt->bind_param('sssi', $name, $email, $phone, $sid);
        $stmt->execute();
        $stmt->close();
        redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Profile updated');
    } elseif ($action === 'change_password') {
        $pwd = (string)($_POST['new_password'] ?? '');
        $pwd2 = (string)($_POST['confirm_password'] ?? '');
        if ($pwd === '' || $pwd !== $pwd2) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Password mismatch', 'error');
        }
        if (strlen($pwd) < 8 || strlen($pwd) > 128) {
            redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Password must be 8-128 characters', 'error');
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
$stmt = db()->prepare('SELECT name, email, phone, status, created_at, last_login_at, last_login_ip FROM super_admins WHERE super_admin_id = ? LIMIT 1');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once ___DIR___ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Super Admin Profile</h3>
            <div class="d-flex align-items-center gap-2">
              <a href="../index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
              <span class="badge bg-danger">SUPER ADMIN</span>
            </div>
          </div>
          <?php if ($flash): ?>
            <div class="alert alert-<?= $flash_type === 'error' ? 'danger' : 'success' ?> mb-3"><?= htmlspecialchars($flash) ?></div>
          <?php endif; ?>

          <div class="text-center mb-4">
            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
              <span style="font-weight:600;">SA</span>
            </div>
            <div class="mt-2 text-muted">@<?= htmlspecialchars($sa['name'] ?? '') ?></div>
          </div>

          <ul class="list-group mb-4">
            <li class="list-group-item d-flex justify-content-between"><span>ID</span><span><?= (int)$sid ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Name</span><span><?= htmlspecialchars($sa['name'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Email</span><span><?= htmlspecialchars($sa['email'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Phone</span><span><?= htmlspecialchars($sa['phone'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Status</span><span><?= htmlspecialchars($sa['status'] ?? '-') ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Last Login</span><span><?= htmlspecialchars($sa['last_login_at'] ?: '-') ?> <?= $sa['last_login_ip'] ? '(' . htmlspecialchars($sa['last_login_ip']) . ')' : '' ?></span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Created</span><span><?= htmlspecialchars($sa['created_at'] ?? '-') ?></span></li>
          </ul>

          <h5 class="mb-3">Edit Profile</h5>
          <form method="post" id="form_profile" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($sa['name'] ?? '') ?>" maxlength="100" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($sa['email'] ?? '') ?>" maxlength="255" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($sa['phone'] ?? '') ?>" inputmode="tel" pattern="^0[7][01245678][0-9]{7}$" maxlength="10" placeholder="07XXXXXXXX">
            </div>
            <div class="col-12">
              <input type="hidden" name="action" value="update_profile">
            </div>
          </form>

          <h5 class="mt-4 mb-3">Change Password</h5>
          <form method="post" id="form_password" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" minlength="8" maxlength="128" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" name="confirm_password" minlength="8" maxlength="128" required>
            </div>
            <div class="col-12 d-flex gap-2">
              <input type="hidden" name="action" value="change_password">
              <button class="btn btn-warning" type="submit">Change Password</button>
              <button class="btn btn-primary" type="submit" form="form_profile">Save Changes</button>

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
