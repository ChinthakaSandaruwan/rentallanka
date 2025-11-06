<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('customer');

[$flash, $flash_type] = (function(){
  if (function_exists('get_flash')) { return get_flash(); }
  return [null, null];
})();

// Recipient listing based on role and search query
$recipients = [];
$selRole = $_GET['role'] ?? '';
if (!in_array($selRole, ['admin','owner','customer'], true)) { $selRole = 'admin'; }
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT user_id, name, email FROM users WHERE role=?';
$params = [$selRole];
if ($q !== '') {
  $sql .= ' AND (name LIKE CONCAT("%", ?, "%") OR email LIKE CONCAT("%", ?, "%"))';
  $params[] = $q; $params[] = $q;
}
$sql .= ' ORDER BY name ASC LIMIT 50';
$stmt = db()->prepare($sql);
if (count($params) === 1) {
  $stmt->bind_param('s', $params[0]);
} else {
  $stmt->bind_param('sss', $params[0], $params[1], $params[2]);
}
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) { $recipients[] = $row; }
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $to_user = (int)($_POST['user_id'] ?? 0);
  $title = trim($_POST['title'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $type = $_POST['type'] ?? 'system';
  if ($to_user <= 0 || $title === '' || $message === '' || !in_array($type, ['system','rental','payment','other'], true)) {
    redirect_with_message($GLOBALS['base_url'] . '/customer/send_notification.php', 'Please complete all fields.', 'error');
  }
  $u = db()->prepare('SELECT 1 FROM users WHERE user_id=? LIMIT 1');
  $u->bind_param('i', $to_user);
  $u->execute();
  $exists = $u->get_result()->fetch_assoc();
  $u->close();
  if (!$exists) {
    redirect_with_message($GLOBALS['base_url'] . '/customer/send_notification.php', 'Recipient not found.', 'error');
  }
  $ins = db()->prepare('INSERT INTO notifications (user_id, title, message, type, rental_id, property_id, is_read) VALUES (?, ?, ?, ?, NULL, NULL, 0)');
  if (!$ins) {
    redirect_with_message($GLOBALS['base_url'] . '/customer/send_notification.php', 'Server error preparing insert.', 'error');
  }
  $ins->bind_param('isss', $to_user, $title, $message, $type);
  if ($ins->execute()) {
    $ins->close();
    redirect_with_message($GLOBALS['base_url'] . '/customer/notification.php', 'Notification sent.', 'success');
  } else {
    $ins->close();
    redirect_with_message($GLOBALS['base_url'] . '/customer/send_notification.php', 'Failed to send notification.', 'error');
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send Notification</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h1 class="h4 mb-3">Send Notification</h1>
  <?php if (!empty($flash)): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
  <form method="get" class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <label class="form-label">Recipient Role</label>
      <select class="form-select" name="role" onchange="this.form.submit()">
        <option value="admin" <?php echo $selRole==='admin'?'selected':''; ?>>Admin</option>
        <option value="owner" <?php echo $selRole==='owner'?'selected':''; ?>>Owner</option>
        <option value="customer" <?php echo $selRole==='customer'?'selected':''; ?>>Customer</option>
      </select>
    </div>
    <div class="col-12 col-md-5">
      <label class="form-label">Search</label>
      <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name or email">
    </div>
    <div class="col-12 col-md-2 align-self-end">
      <button type="submit" class="btn btn-secondary w-100">Search</button>
    </div>
  </form>

  <form method="post" class="row g-3">
    <div class="col-12 col-md-6">
      <label class="form-label">Recipient</label>
      <select class="form-select" name="user_id" required>
        <option value="">-- Select user --</option>
        <?php foreach ($recipients as $u): ?>
          <option value="<?php echo (int)$u['user_id']; ?>"><?php echo htmlspecialchars(($u['name'] ?: 'User') . ' - ' . ($u['email'] ?? '')); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Showing up to 50 matches.</div>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Type</label>
      <select class="form-select" name="type">
        <option value="system">System</option>
        <option value="rental">Rental</option>
        <option value="payment">Payment</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label">Title</label>
      <input type="text" class="form-control" name="title" maxlength="150" required>
    </div>
    <div class="col-12">
      <label class="form-label">Message</label>
      <textarea class="form-control" name="message" rows="5" required></textarea>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Send</button>
      <a href="<?= $base_url ?>/customer/notification.php" class="btn btn-secondary">Back</a>
    </div>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

