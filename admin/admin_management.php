<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');
require_once __DIR__ . '/../config/config.php';

$role = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($action === 'create') {
        if ($phone === '' || !in_array($status, ['active','inactive','banned'], true)) {
            redirect_with_message('admin_management.php', 'Invalid input', 'error');
        }
        $stmt = db()->prepare("INSERT INTO users (email, phone, role, status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $email, $phone, $role, $status);
        if (!$stmt->execute()) {
            redirect_with_message('admin_management.php', 'Create failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('admin_management.php', 'Admin created');
    }

    if ($action === 'update') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0 || $phone === '' || !in_array($status, ['active','inactive','banned'], true)) {
            redirect_with_message('admin_management.php', 'Invalid input', 'error');
        }
        $stmt = db()->prepare("UPDATE users SET email=?, phone=?, status=? WHERE user_id=? AND role=?");
        $stmt->bind_param('sssis', $email, $phone, $status, $user_id, $role);
        if (!$stmt->execute()) {
            redirect_with_message('admin_management.php', 'Update failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('admin_management.php', 'Admin updated');
    }
}

if (($_GET['action'] ?? '') === 'delete') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id > 0) {
        $cnt = 0;
        $c = db()->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
        if ($c) { $rowc = $c->fetch_assoc(); $cnt = (int)($rowc['c'] ?? 0); }
        if ($cnt <= 1) {
            redirect_with_message('admin_management.php', 'At least one admin is required', 'error');
        }
        $stmt = db()->prepare("DELETE FROM users WHERE user_id=? AND role=?");
        $stmt->bind_param('is', $user_id, $role);
        if (!$stmt->execute()) {
            redirect_with_message('admin_management.php', 'Delete failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('admin_management.php', 'Admin deleted');
    } else {
        redirect_with_message('admin_management.php', 'Invalid admin id', 'error');
    }
}

list($flash, $flashType) = get_flash();
$q = trim($_GET['q'] ?? '');
$list = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $res = db()->prepare("SELECT user_id, email, phone, status, created_at
                           FROM users
                           WHERE role=? AND (email LIKE ? OR phone LIKE ?)
                           ORDER BY user_id DESC");
    $res->bind_param('sss', $role, $like, $like);
} else {
    $res = db()->prepare("SELECT user_id, email, phone, status, created_at FROM users WHERE role=? ORDER BY user_id DESC");
    $res->bind_param('s', $role);
}
$res->execute();
$result = $res->get_result();
while ($row = $result->fetch_assoc()) { $list[] = $row; }

$editItem = null;
if (($_GET['action'] ?? '') === 'edit') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id > 0) {
        $stmt = db()->prepare("SELECT user_id, email, phone, status FROM users WHERE user_id=? AND role=?");
        $stmt->bind_param('is', $user_id, $role);
        $stmt->execute();
        $editItem = $stmt->get_result()->fetch_assoc();
    }
}
$admin_count = 0;
$rc = db()->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
if ($rc) { $rr = $rc->fetch_assoc(); $admin_count = (int)($rr['c'] ?? 0); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Admin Management</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?php echo $flashType === 'error' ? 'danger' : 'success'; ?>" role="alert">
        <?php echo htmlspecialchars($flash); ?>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <form method="get" class="row g-2">
        <div class="col-auto">
          <input type="text" name="q" class="form-control" placeholder="Search email or phone" value="<?php echo htmlspecialchars($q); ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-outline-primary">Search</button>
          <?php if ($q !== ''): ?>
            <a href="admin_management.php" class="btn btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card mb-4">
      <div class="card-header"><?php echo $editItem ? 'Edit Admin' : 'Create Admin'; ?></div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
          <?php if ($editItem): ?>
            <input type="hidden" name="user_id" value="<?php echo (int)$editItem['user_id']; ?>">
          <?php endif; ?>

          <div class="col-12 col-md-4">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($editItem['email'] ?? ''); ?>" placeholder="optional">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Phone<span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($editItem['phone'] ?? ''); ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Status<span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
              <?php $statuses=['active','inactive','banned']; $sel=$editItem['status']??'active'; foreach($statuses as $s){
                echo '<option value="'.htmlspecialchars($s).'"'.($sel===$s?' selected':'').'>'.htmlspecialchars(ucfirst($s)).'</option>'; }
              ?>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo $editItem ? 'Update' : 'Create'; ?></button>
            <?php if ($editItem): ?>
              <a class="btn btn-outline-secondary" href="admin_management.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Admins</div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Email</th>
              <th scope="col">Phone</th>
              <th scope="col">Status</th>
              <th scope="col">Created</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $row): ?>
              <tr>
                <td><?php echo (int)$row['user_id']; ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td>
                  <span class="badge <?php echo $row['status']==='active'?'bg-success':($row['status']==='inactive'?'bg-secondary':'bg-danger'); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                </td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="admin_management.php?action=edit&user_id=<?php echo (int)$row['user_id']; ?>">Edit</a>
                  <?php if ($admin_count > 1): ?>
                    <a class="btn btn-sm btn-outline-danger" href="admin_management.php?action=delete&user_id=<?php echo (int)$row['user_id']; ?>" onclick="return confirm('Delete this admin?');">Delete</a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

