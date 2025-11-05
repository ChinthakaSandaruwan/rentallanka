<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('customer');
require_once __DIR__ . '/../config/config.php';

$customer_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($customer_id <= 0) {
  redirect_with_message('../auth/login.php', 'Please login', 'error');
}

$error = '';
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $rid = (int)($_POST['rental_id'] ?? 0);
    $pid = (int)($_POST['property_id'] ?? 0);
    if ($rid > 0 && $pid > 0) {
      // Mark rental completed (only if belongs to this customer)
      $u = db()->prepare('UPDATE rentals SET status="completed" WHERE rental_id=? AND customer_id=?');
      $u->bind_param('ii', $rid, $customer_id);
      $ok1 = $u->execute();
      $u->close();
      // Make property available again
      $p = db()->prepare('UPDATE properties SET status="available" WHERE property_id=?');
      $p->bind_param('i', $pid);
      $ok2 = $p->execute();
      $p->close();
      if ($ok1 && $ok2) {
        redirect_with_message($GLOBALS['base_url'] . '/customer/my_rented_property.php', 'Rental closed', 'success');
      } else {
        $error = 'Failed to close rental';
      }
    } else {
      $error = 'Bad input';
    }
  }
}

$rows = [];
$sql = 'SELECT r.rental_id, r.property_id, r.start_date, r.end_date, r.total_price, r.status, r.created_at,
               p.title
        FROM rentals r
        JOIN properties p ON p.property_id = r.property_id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Rented Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">My Rented Properties</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Property</th>
              <th>Period</th>
              <th>Total</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['rental_id']; ?></td>
                <td><?php echo htmlspecialchars($r['title'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($r['start_date']); ?> → <?php echo htmlspecialchars($r['end_date'] ?? ''); ?></td>
                <td>LKR <?php echo number_format((float)$r['total_price'], 2); ?></td>
                <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td class="text-nowrap">
                  <?php if (($r['status'] ?? '') === 'active'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Mark this rental as completed?');">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="rental_id" value="<?php echo (int)$r['rental_id']; ?>">
                      <input type="hidden" name="property_id" value="<?php echo (int)$r['property_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">I'm Left</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-center py-4">No property rentals yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
