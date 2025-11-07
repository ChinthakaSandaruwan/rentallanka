<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('admin'); ?>
<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Packages Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Packages Management</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
  </div>

  <?php
    $alert = ['type' => '', 'msg' => ''];
    $is_post = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
    if ($is_post) {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'create' || $action === 'update') {
                $package_name = trim((string)($_POST['package_name'] ?? ''));
                $type_choice = trim((string)($_POST['type_choice'] ?? 'property')); // property | room
                $duration_choice = trim((string)($_POST['duration_choice'] ?? 'monthly')); // monthly | yearly
                $max_count = (int)($_POST['max_count'] ?? 0);
                $price = (float)($_POST['price'] ?? 0);
                $description = (string)($_POST['description'] ?? '');
                $status = trim((string)($_POST['status'] ?? 'active'));

                if ($package_name === '') { throw new Exception('Package name required'); }
                if (!in_array($type_choice, ['property','room'], true)) { throw new Exception('Invalid type'); }
                if (!in_array($duration_choice, ['monthly','yearly'], true)) { throw new Exception('Invalid duration'); }
                if ($max_count < 0) { throw new Exception('Max Advertising Count must be >= 0'); }
                $allowed_status = ['active','inactive'];
                if (!in_array($status, $allowed_status, true)) { throw new Exception('Invalid status'); }

                // Map to schema
                $package_type = $duration_choice; // monthly|yearly
                $duration_days = ($duration_choice === 'monthly') ? 30 : 365;
                $max_properties = ($type_choice === 'property') ? $max_count : 0;
                $max_rooms = ($type_choice === 'room') ? $max_count : 0;

                if ($action === 'create') {
                    $stmt = db()->prepare('INSERT INTO packages (package_name, package_type, duration_days, max_properties, max_rooms, price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssiiidss', $package_name, $package_type, $duration_days, $max_properties, $max_rooms, $price, $description, $status);
                    $stmt->execute();
                    $stmt->close();
                    $alert = ['type' => 'success', 'msg' => 'Package created'];
                } else {
                    $pid = (int)($_POST['package_id'] ?? 0);
                    if ($pid <= 0) { throw new Exception('Invalid package id'); }
                    $stmt = db()->prepare('UPDATE packages SET package_name=?, package_type=?, duration_days=?, max_properties=?, max_rooms=?, price=?, description=?, status=? WHERE package_id=?');
                    $stmt->bind_param('ssiiidssi', $package_name, $package_type, $duration_days, $max_properties, $max_rooms, $price, $description, $status, $pid);
                    $stmt->execute();
                    $stmt->close();
                    $alert = ['type' => 'success', 'msg' => 'Package updated'];
                }
            } elseif ($action === 'delete') {
                $pid = (int)($_POST['package_id'] ?? 0);
                if ($pid <= 0) { throw new Exception('Invalid package id'); }
                $del = db()->prepare('DELETE FROM packages WHERE package_id=?');
                $del->bind_param('i', $pid);
                $del->execute();
                $del->close();
                $alert = ['type' => 'success', 'msg' => 'Package deleted'];
            }
        } catch (Throwable $e) {
            $alert = ['type' => 'danger', 'msg' => htmlspecialchars($e->getMessage())];
        }
    }

    $editId = (int)($_GET['edit'] ?? 0);
    $edit = null;
    if ($editId > 0) {
        $st = db()->prepare('SELECT * FROM packages WHERE package_id=? LIMIT 1');
        $st->bind_param('i', $editId);
        $st->execute();
        $edit = $st->get_result()->fetch_assoc();
        $st->close();
    }

    $rows = [];
    try {
        $res = db()->query('SELECT * FROM packages ORDER BY created_at DESC');
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
    } catch (Throwable $e) { $rows = []; }
  ?>

  <?php if ($alert['msg'] !== ''): ?>
    <div class="alert alert-<?= $alert['type'] ?>"><?= $alert['msg'] ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3"><?= $edit ? 'Edit Package' : 'Create New Package' ?></h5>
      <form method="post" class="row g-3">
        <?php if ($edit): ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="package_id" value="<?= (int)$edit['package_id'] ?>">
        <?php else: ?>
          <input type="hidden" name="action" value="create">
        <?php endif; ?>
        <div class="col-12 col-md-6">
          <label class="form-label">Package Name</label>
          <input type="text" name="package_name" class="form-control" value="<?= htmlspecialchars((string)($edit['package_name'] ?? '')) ?>" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Type</label>
          <?php $typeGuess = ((int)($edit['max_rooms'] ?? 0) > 0) ? 'room' : 'property'; ?>
          <select name="type_choice" class="form-select" required>
            <option value="property" <?= $typeGuess==='property'?'selected':'' ?>>Property</option>
            <option value="room" <?= $typeGuess==='room'?'selected':'' ?>>Room</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Duration</label>
          <?php $durGuess = (string)($edit['package_type'] ?? 'monthly'); ?>
          <select name="duration_choice" class="form-select" required>
            <option value="monthly" <?= $durGuess==='monthly'?'selected':'' ?>>Monthly</option>
            <option value="yearly" <?= $durGuess==='yearly'?'selected':'' ?>>Yearly</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Max Advertising Count</label>
          <?php $maxCountGuess = (int)max((int)($edit['max_properties'] ?? 0),(int)($edit['max_rooms'] ?? 0)); ?>
          <input type="number" name="max_count" class="form-control" value="<?= $maxCountGuess ?>" min="0" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Price (LKR)</label>
          <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars((string)($edit['price'] ?? '0.00')) ?>" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Status</label>
          <?php $st = (string)($edit['status'] ?? 'active'); ?>
          <select name="status" class="form-select">
            <option value="active" <?= $st==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $st==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string)($edit['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <?php if ($edit): ?>
            <a href="packages_management.php" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary" type="submit">Update</button>
          <?php else: ?>
            <button class="btn btn-primary" type="submit">Create</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-3">All Packages</h5>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Duration</th>
              <th>Max Adv. Count</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['package_id'] ?></td>
                <td><?= htmlspecialchars((string)$r['package_name']) ?></td>
                <?php $typeLbl = ((int)$r['max_rooms'] > 0) ? 'Room' : 'Property'; ?>
                <td><span class="badge text-bg-secondary"><?= $typeLbl ?></span></td>
                <?php $durLbl = ((string)$r['package_type'] === 'yearly') ? 'Yearly' : 'Monthly'; ?>
                <td><?= $durLbl ?></td>
                <td><?= (int)max((int)$r['max_properties'], (int)$r['max_rooms']) ?></td>
                <td>LKR <?= number_format((float)$r['price'], 2) ?></td>
                <td>
                  <?php if (($r['status'] ?? '') === 'active'): ?>
                    <span class="badge text-bg-success">active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">inactive</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
                <td class="text-end">
                  <a href="packages_management.php?edit=<?= (int)$r['package_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this package?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="package_id" value="<?= (int)$r['package_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

