<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');
require_once __DIR__ . '/../config/config.php';

$owner_id = (int)($_SESSION['user']['user_id'] ?? 0);
if ($owner_id <= 0) {
    redirect_with_message('../auth/login.php', 'Please login', 'error');
}

$flash = '';
$type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $allowed = ['pending','confirmed','cancelled','completed'];
        if ($booking_id > 0 && in_array($new_status, $allowed, true)) {
            // Ensure the booking belongs to this owner
            $sql = "UPDATE bookings b
                    JOIN property_units u ON u.unit_id = b.unit_id
                    JOIN properties p ON p.property_id = u.property_id
                    SET b.status = ?
                    WHERE b.booking_id = ? AND p.owner_id = ?";
            $stmt = db()->prepare($sql);
            $stmt->bind_param('sii', $new_status, $booking_id, $owner_id);
            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $flash = 'Booking status updated';
            } else {
                $flash = 'Failed to update status';
                $type = 'error';
            }
            $stmt->close();
        } else {
            $flash = 'Invalid input';
            $type = 'error';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$where = 'WHERE p.owner_id = ?';
$params = [$owner_id];
$types = 'i';
if ($q !== '') {
    $where .= ' AND (c.phone LIKE ? OR c.email LIKE ? OR pr.title LIKE ? OR u.name LIKE ?)';
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}

$sql = "SELECT b.booking_id, b.booking_type, b.start_date, b.end_date, b.total_price,
               b.with_meal, b.meal_plan, b.meal_notes, b.status, b.created_at,
               pr.title AS property_title, u.name AS unit_name,
               c.user_id AS customer_id, c.email AS customer_email, c.phone AS customer_phone
        FROM bookings b
        JOIN property_units u ON u.unit_id = b.unit_id
        JOIN properties pr ON pr.property_id = u.property_id
        JOIN users c ON c.user_id = b.customer_id
        JOIN properties p ON p.property_id = u.property_id
        $where
        ORDER BY b.created_at DESC
        LIMIT 300";

$stmt = db()->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner - Booking Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Booking Management</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?php echo $type === 'error' ? 'danger' : 'success'; ?>" role="alert">
        <?php echo htmlspecialchars($flash); ?>
      </div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-3">
      <div class="col-auto">
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search customer, property, or unit">
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <?php if ($q !== ''): ?>
          <a href="booking_management.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="card">
      <div class="card-header">Bookings</div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Property / Unit</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Dates</th>
              <th>Total</th>
              <th>Meal</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['booking_id']; ?></td>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars($r['property_title'] ?? ''); ?></div>
                  <div class="text-muted small"><?php echo htmlspecialchars($r['unit_name'] ?? ''); ?></div>
                </td>
                <td>
                  <div><?php echo htmlspecialchars($r['customer_email'] ?? ''); ?></div>
                  <div class="text-muted small"><?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?></div>
                </td>
                <td><?php echo htmlspecialchars($r['booking_type']); ?></td>
                <td>
                  <div><?php echo htmlspecialchars($r['start_date']); ?> → <?php echo htmlspecialchars($r['end_date']); ?></div>
                </td>
                <td>Rs. <?php echo number_format((float)$r['total_price'], 2); ?></td>
                <td>
                  <?php if ((int)$r['with_meal'] === 1): ?>
                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars($r['meal_plan']); ?></span>
                  <?php else: ?>
                    <span class="text-muted">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?php
                    echo $r['status']==='confirmed'?'bg-success':($r['status']==='pending'?'bg-secondary':($r['status']==='cancelled'?'bg-danger':'bg-primary')); ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                </td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="booking_id" value="<?php echo (int)$r['booking_id']; ?>">
                    <select name="status" class="form-select form-select-sm d-inline-block w-auto">
                      <?php $opts=['pending','confirmed','cancelled','completed']; foreach($opts as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $r['status']===$s?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($s)); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                  </form>
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
