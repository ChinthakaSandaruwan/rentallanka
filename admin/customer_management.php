<?php
require_once __DIR__ . '/includes/config.php';

$role = 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($action === 'create') {
        if ($phone === '' || !in_array($status, ['active','inactive','banned'], true)) {
            redirect_with_message('customer_management.php', 'Invalid input', 'error');
        }
        $stmt = db()->prepare("INSERT INTO users (email, phone, role, status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $email, $phone, $role, $status);
        if (!$stmt->execute()) {
            redirect_with_message('customer_management.php', 'Create failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('customer_management.php', 'Customer created');
    }

    if ($action === 'update') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0 || $phone === '' || !in_array($status, ['active','inactive','banned'], true)) {
            redirect_with_message('customer_management.php', 'Invalid input', 'error');
        }
        $stmt = db()->prepare("UPDATE users SET email=?, phone=?, status=? WHERE user_id=? AND role=?");
        $stmt->bind_param('sssis', $email, $phone, $status, $user_id, $role);
        if (!$stmt->execute()) {
            redirect_with_message('customer_management.php', 'Update failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('customer_management.php', 'Customer updated');
    }
}

if (($_GET['action'] ?? '') === 'delete') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if ($user_id > 0) {
        $stmt = db()->prepare("DELETE FROM users WHERE user_id=? AND role=?");
        $stmt->bind_param('is', $user_id, $role);
        if (!$stmt->execute()) {
            redirect_with_message('customer_management.php', 'Delete failed: ' . $stmt->error, 'error');
        }
        redirect_with_message('customer_management.php', 'Customer deleted');
    } else {
        redirect_with_message('customer_management.php', 'Invalid customer id', 'error');
    }
}

list($flash, $flashType) = get_flash();

$list = [];
$res = db()->prepare("SELECT user_id, email, phone, status, created_at FROM users WHERE role=? ORDER BY user_id DESC");
$res->bind_param('s', $role);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Management</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 16px; }
    .flash { padding: 10px; margin-bottom: 12px; border-radius: 6px; }
    .flash.success { background: #e8f6ee; color: #0a7a3c; }
    .flash.error { background: #fdecea; color: #b42318; }
    form { display: grid; gap: 8px; max-width: 420px; margin-bottom: 24px; }
    input, select, button { padding: 8px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; }
    th { background: #f5f5f5; text-align: left; }
    a.button { padding: 6px 10px; border: 1px solid #999; border-radius: 4px; text-decoration: none; }
  </style>
  </head>
<body>
  <h2>Customer Management</h2>
  <?php if ($flash): ?>
    <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <h3><?php echo $editItem ? 'Edit Customer' : 'Create Customer'; ?></h3>
  <form method="post">
    <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
    <?php if ($editItem): ?>
      <input type="hidden" name="user_id" value="<?php echo (int)$editItem['user_id']; ?>">
    <?php endif; ?>
    <label>Email
      <input type="email" name="email" value="<?php echo htmlspecialchars($editItem['email'] ?? ''); ?>" placeholder="optional">
    </label>
    <label>Phone*
      <input type="text" name="phone" value="<?php echo htmlspecialchars($editItem['phone'] ?? ''); ?>" required>
    </label>
    <label>Status*
      <select name="status" required>
        <?php $statuses=['active','inactive','banned']; $sel=$editItem['status']??'active'; foreach($statuses as $s){
          echo '<option value="'.htmlspecialchars($s).'"'.($sel===$s?' selected':'').'>'.htmlspecialchars(ucfirst($s)).'</option>'; }
        ?>
      </select>
    </label>
    <button type="submit"><?php echo $editItem ? 'Update' : 'Create'; ?></button>
    <?php if ($editItem): ?>
      <a class="button" href="customer_management.php">Cancel</a>
    <?php endif; ?>
  </form>

  <h3>Customers</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $row): ?>
        <tr>
          <td><?php echo (int)$row['user_id']; ?></td>
          <td><?php echo htmlspecialchars($row['email']); ?></td>
          <td><?php echo htmlspecialchars($row['phone']); ?></td>
          <td><?php echo htmlspecialchars($row['status']); ?></td>
          <td><?php echo htmlspecialchars($row['created_at']); ?></td>
          <td>
            <a class="button" href="customer_management.php?action=edit&user_id=<?php echo (int)$row['user_id']; ?>">Edit</a>
            <a class="button" href="customer_management.php?action=delete&user_id=<?php echo (int)$row['user_id']; ?>" onclick="return confirm('Delete this customer?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>

