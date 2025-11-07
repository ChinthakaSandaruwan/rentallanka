<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../config/config.php';

$action = $_POST['action'] ?? '';
[$flash, $flash_type] = get_flash();
$error = '';

// Handle CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create_sa') {
        // Enforce max 2 super admins
        $cstmt = db()->prepare('SELECT COUNT(*) AS c FROM super_admins');
        $cstmt->execute();
        $cres = $cstmt->get_result()->fetch_assoc();
        $cstmt->close();
        if ((int)($cres['c'] ?? 0) >= 2) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Maximum super admins (2) reached', 'error');
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = in_array($_POST['status'] ?? 'active', ['active','inactive','banned']) ? $_POST['status'] : 'active';
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || $email === '' || $password === '') {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'name, email and password are required', 'error');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = db()->prepare('INSERT INTO super_admins (email, name, password_hash, phone, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('sssss', $email, $name, $hash, $phone, $status);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if ($ok) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Super admin created');
        } else {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Create failed: ' . $err, 'error');
        }
    } elseif ($action === 'update_sa') {
        $id = (int)($_POST['super_admin_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = in_array($_POST['status'] ?? 'active', ['active','inactive','banned']) ? $_POST['status'] : 'active';
        $password = (string)($_POST['password'] ?? '');
        if ($id <= 0) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Invalid ID', 'error');
        }
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = db()->prepare('UPDATE super_admins SET email = ?, phone = ?, status = ?, password_hash = ? WHERE super_admin_id = ?');
            $stmt->bind_param('ssssi', $email, $phone, $status, $hash, $id);
        } else {
            $stmt = db()->prepare('UPDATE super_admins SET email = ?, phone = ?, status = ? WHERE super_admin_id = ?');
            $stmt->bind_param('sssi', $email, $phone, $status, $id);
        }
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if ($ok) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Super admin updated');
        } else {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Update failed: ' . $err, 'error');
        }
    } elseif ($action === 'delete_sa') {
        $id = (int)($_POST['super_admin_id'] ?? 0);
        $self = (int)($_SESSION['super_admin_id'] ?? 0);
        if ($id <= 0) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Invalid ID', 'error');
        }
        // Do not allow deleting the last remaining super admin
        $cstmt = db()->prepare('SELECT COUNT(*) AS c FROM super_admins');
        $cstmt->execute();
        $cres = $cstmt->get_result()->fetch_assoc();
        $cstmt->close();
        if ((int)($cres['c'] ?? 0) <= 1) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'At least one super admin is required', 'error');
        }
        if ($id === $self) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'You cannot delete your own account', 'error');
        }
        $stmt = db()->prepare('DELETE FROM super_admins WHERE super_admin_id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if ($ok) {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Super admin deleted');
        } else {
            redirect_with_message($base_url . '/superAdmin/super_admin_management.php', 'Delete failed: ' . $err, 'error');
        }
    }
}

// Fetch list and optionally selected record for editing
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;
if ($edit_id > 0) {
    $stmt = db()->prepare('SELECT super_admin_id, email, name, phone, status, created_at, last_login_at, last_login_ip FROM super_admins WHERE super_admin_id = ?');
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_row = $res->fetch_assoc();
    $stmt->close();
}

$stmt = db()->prepare('SELECT super_admin_id, email, name, phone, status, created_at FROM super_admins ORDER BY super_admin_id ASC');
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$sa_count = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Super Admin Management</h1>
    <a class="btn btn-outline-secondary btn-sm" href="index.php">Back</a>
  </div>
  <?php if ($flash): ?><div class="alert alert-<?= $flash_type === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-12 order-1">
      <div class="card h-100">
        <div class="card-header"><?= $edit_row ? 'Edit Super Admin' : 'Create Super Admin' ?></div>
        <div class="card-body">
          <?php if ($edit_row): ?>
            <form method="post" class="vstack gap-3">
              <div>
                <label class="form-label">name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($edit_row['name']) ?>" disabled>
                <div class="form-text">name cannot be changed.</div>
              </div>
              <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_row['email']) ?>" required>
              </div>
              <div>
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_row['phone']) ?>">
              </div>
              <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="active" <?= $edit_row['status']==='active'?'selected':''; ?>>Active</option>
                  <option value="inactive" <?= $edit_row['status']==='inactive'?'selected':''; ?>>Inactive</option>
                  <option value="banned" <?= $edit_row['status']==='banned'?'selected':''; ?>>Banned</option>
                </select>
              </div>
              <div>
                <label class="form-label">New Password (optional)</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
              </div>
              <input type="hidden" name="action" value="update_sa">
              <input type="hidden" name="super_admin_id" value="<?= (int)$edit_row['super_admin_id'] ?>">
              <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Update</button>
                <a class="btn btn-secondary" href="super_admin_management.php">Cancel</a>
              </div>
            </form>
          <?php else: ?>
            <?php if ($sa_count >= 2): ?>
              <div class="alert alert-warning mb-0">Maximum super admins (2) reached. Delete or edit existing accounts to proceed.</div>
            <?php else: ?>
              <form method="post" class="vstack gap-3">
                <div>
                  <label class="form-label">name</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
                <div>
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" required>
                </div>
                <div>
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control">
                </div>
                <div>
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="banned">Banned</option>
                  </select>
                </div>
                <div>
                  <label class="form-label">Password</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <input type="hidden" name="action" value="create_sa">
                <button class="btn btn-primary" type="submit">Create</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 order-2">
      <div class="card h-100">
        <div class="card-header">Super Admins</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= (int)$r['super_admin_id'] ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td><?= htmlspecialchars($r['phone']) ?></td>
                    <td><span class="badge <?= $r['status']==='active'?'bg-success':($r['status']==='inactive'?'bg-secondary':'bg-danger') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                      <a class="btn btn-sm btn-outline-primary" href="super_admin_management.php?edit=<?= (int)$r['super_admin_id'] ?>">Edit</a>
                      <?php if ($sa_count > 1 && (int)$r['super_admin_id'] !== (int)($_SESSION['super_admin_id'] ?? 0)): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this super admin?');">
                          <input type="hidden" name="action" value="delete_sa">
                          <input type="hidden" name="super_admin_id" value="<?= (int)$r['super_admin_id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                      <?php else: ?>
                        <button class="btn btn-sm btn-outline-danger" type="button" disabled title="Cannot delete the last or current super admin">Delete</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
