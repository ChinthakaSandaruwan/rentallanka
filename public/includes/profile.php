<?php
require_once __DIR__ . '/../../config/config.php';

$isSuper = isset($_SESSION['super_admin_id']) && (int)$_SESSION['super_admin_id'] > 0;
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$isSuper && !$loggedIn) {
    redirect_with_message($base_url . '/auth/login.php', 'Please log in first', 'error');
}

$role = $_SESSION['role'] ?? ($isSuper ? 'super_admin' : '');
$user = $_SESSION['user'] ?? null; // for non-super users

// Prepare display data
$display = [
    'role' => $role,
    'name' => '',
    'nic' => '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'id' => $user['user_id'] ?? null,
    'profile_image' => $user['profile_image'] ?? '',
    'status' => '',
    'created_at' => '',
];

// For non-super users, refresh details from DB (including profile_image)
if (!$isSuper && $display['id'] !== null) {
    $uid = (int)$display['id'];
    $stmtU = db()->prepare('SELECT name, nic, email, phone, profile_image, role, status, created_at FROM users WHERE user_id = ? LIMIT 1');
    $stmtU->bind_param('i', $uid);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    if ($rowU = $resU->fetch_assoc()) {
        $display['name'] = (string)($rowU['name'] ?? '');
        $display['nic'] = (string)($rowU['nic'] ?? '');
        $display['email'] = (string)($rowU['email'] ?? $display['email']);
        $display['phone'] = (string)($rowU['phone'] ?? $display['phone']);
        $display['profile_image'] = (string)($rowU['profile_image'] ?? $display['profile_image']);
        $display['role'] = (string)($rowU['role'] ?? $display['role']);
        $display['status'] = (string)($rowU['status'] ?? '');
        $display['created_at'] = (string)($rowU['created_at'] ?? '');
    }
    $stmtU->close();
}

if ($isSuper) {
    $sid = (int)$_SESSION['super_admin_id'];
    $stmt = db()->prepare('SELECT name, email, phone, status, created_at FROM super_admins WHERE super_admin_id = ? LIMIT 1');
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $sa = $res->fetch_assoc();
    $stmt->close();
    if ($sa) {
        $display['role'] = 'super_admin';
        $display['name'] = (string)($sa['name'] ?? '');
        $display['email'] = (string)($sa['email'] ?? '');
        $display['phone'] = (string)($sa['phone'] ?? '');
        $display['id'] = $sid;
        $display['status'] = (string)($sa['status'] ?? '');
        $display['created_at'] = (string)($sa['created_at'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($isSuper) {
        if ($action === 'update_super_admin_profile') {
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $stmt = db()->prepare('UPDATE super_admins SET email = ?, phone = ? WHERE super_admin_id = ?');
            $stmt->bind_param('ssi', $email, $phone, $display['id']);
            $stmt->execute();
            $stmt->close();
            redirect_with_message($base_url . '/public/includes/profile.php', 'Profile updated');
        } elseif ($action === 'change_super_admin_password') {
            $pwd = (string)($_POST['new_password'] ?? '');
            $pwd2 = (string)($_POST['confirm_password'] ?? '');
            if ($pwd === '' || $pwd !== $pwd2) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Password mismatch', 'error');
            }
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = db()->prepare('UPDATE super_admins SET password_hash = ? WHERE super_admin_id = ?');
            $stmt->bind_param('si', $hash, $display['id']);
            $stmt->execute();
            $stmt->close();
            redirect_with_message($base_url . '/public/includes/profile.php', 'Password changed');
        }
    } else {
        if ($action === 'update_user_profile') {
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $uid = (int)($display['id'] ?? 0);
            if ($uid <= 0) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid user', 'error');
            }
            $img_path = null;
            if (isset($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid image', 'error');
                }
                $dir_map = [
                    'admin' => __DIR__ . '/../../uploads/admin_profile_photo',
                    'owner' => __DIR__ . '/../../uploads/owner_profile_photo',
                    'customer' => __DIR__ . '/../../uploads/user_profile_photo',
                ];
                $target_dir = $dir_map[$display['role']] ?? (__DIR__ . '/../../uploads/user_profile_photo');
                if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
                $fname = 'u' . $uid . '_' . time() . '.' . $ext;
                $dest = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    $rel_base = str_replace('\\', '/', str_replace(__DIR__ . '/../../', '', $target_dir));
                    $img_path = $rel_base . '/' . $fname;
                }
            }
            if ($img_path !== null) {
                $stmt = db()->prepare('UPDATE users SET email = ?, phone = ?, profile_image = ? WHERE user_id = ?');
                $stmt->bind_param('sssi', $email, $phone, $img_path, $uid);
            } else {
                $stmt = db()->prepare('UPDATE users SET email = ?, phone = ? WHERE user_id = ?');
                $stmt->bind_param('ssi', $email, $phone, $uid);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['email'] = $email;
            redirect_with_message($base_url . '/public/includes/profile.php', 'Profile updated');
        } elseif ($action === 'delete_user_account') {
            $uid = (int)($display['id'] ?? 0);
            if ($uid <= 0) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid user', 'error');
            }
            $stmt = db()->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            session_destroy();
            redirect_with_message($base_url . '/index.php', 'Account deleted');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="mb-3 d-flex align-items-center gap-2">
            <?php
              $badgeClass = 'bg-secondary';
              if ($display['role'] === 'super_admin') $badgeClass = 'bg-danger';
              elseif ($display['role'] === 'admin') $badgeClass = 'bg-danger';
              elseif ($display['role'] === 'owner') $badgeClass = 'bg-success';
              elseif ($display['role'] === 'customer') $badgeClass = 'bg-primary';
            ?>
            <span class="badge <?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($display['role'] ?: 'unknown') ?></span>
            <?php if (!empty($display['name'])): ?>
              <span class="text-muted"><?= htmlspecialchars($display['name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="text-center mb-3">
            <?php if (!$isSuper && !empty($display['profile_image'])): ?>
              <img src="<?= $base_url . '/' . ltrim($display['profile_image'], '/') ?>" alt="Profile" class="rounded-circle" style="width: 96px; height:96px; object-fit: cover;">
            <?php else: ?>
              <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
                <span style="font-weight:600;"><?= $isSuper ? 'SA' : 'No Image' ?></span>
              </div>
            <?php endif; ?>
          </div>
          <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>ID</span><span><?= $display['id'] !== null ? (int)$display['id'] : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Name</span><span><?= $display['name'] !== '' ? htmlspecialchars($display['name']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>NIC</span><span><?= $display['nic'] !== '' ? htmlspecialchars($display['nic']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Email</span><span><?= $display['email'] !== '' ? htmlspecialchars($display['email']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Phone</span><span><?= $display['phone'] !== '' ? htmlspecialchars($display['phone']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Role</span><span><?= $display['role'] !== '' ? htmlspecialchars($display['role']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Status</span><span><?= $display['status'] !== '' ? htmlspecialchars($display['status']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Created</span><span><?= $display['created_at'] !== '' ? htmlspecialchars($display['created_at']) : '-' ?></span>
            </li>
          </ul>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-secondary" href="<?= $base_url ?>/">Home</a>
            <?php if ($display['role'] === 'super_admin'): ?>
              <a class="btn btn-outline-danger" href="<?= $base_url ?>/superAdmin/index.php">Super Admin Dashboard</a>
            <?php elseif ($display['role'] === 'admin'): ?>
              <a class="btn btn-outline-danger" href="<?= $base_url ?>/admin/index.php">Admin Dashboard</a>
            <?php elseif ($display['role'] === 'owner'): ?>
              <a class="btn btn-outline-success" href="<?= $base_url ?>/owner/index.php">Owner Dashboard</a>
            <?php elseif ($display['role'] === 'customer'): ?>
              <a class="btn btn-outline-primary" href="<?= $base_url ?>/user/index.php">Customer Dashboard</a>
            <?php endif; ?>
            <a class="btn btn-outline-danger ms-auto" href="<?= $base_url ?>/auth/logout.php">Logout</a>
          </div>

          <?php if ($display['role'] === 'super_admin'): ?>
          <hr>
          <h5 class="mb-3">Edit Profile</h5>
          <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($display['email']) ?>" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($display['phone']) ?>" />
            </div>
            <div class="col-12">
              <input type="hidden" name="action" value="update_super_admin_profile" />
              <button class="btn btn-primary" type="submit">Save</button>
            </div>
          </form>
          <h5 class="mt-4">Change Password</h5>
          <form method="post" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" required />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" name="confirm_password" required />
            </div>
            <div class="col-12">
              <input type="hidden" name="action" value="change_super_admin_password" />
              <button class="btn btn-warning" type="submit">Change Password</button>
            </div>
          </form>
          <?php else: ?>
          <hr>
          <h5 class="mb-3">Edit Profile</h5>
          <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($display['email']) ?>" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($display['phone']) ?>" />
            </div>
            <div class="col-12">
              <label class="form-label">Profile Image</label>
              <input type="file" class="form-control" name="profile_image" accept="image/*" />
            </div>
            <div class="col-12 d-flex gap-2">
              <input type="hidden" name="action" value="update_user_profile" />
              <button class="btn btn-primary" type="submit">Save</button>
              <?php if ($display['role'] === 'customer'): ?>
                <button class="btn btn-outline-danger" name="action" value="delete_user_account" type="submit" onclick="return confirm('Delete your account? This cannot be undone.');">Delete Account</button>
              <?php endif; ?>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
