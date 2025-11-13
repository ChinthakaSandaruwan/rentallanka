<?php
require_once ___DIR___ . '/../config/config.php';

function get_owners(): array {
  $rows = [];
  try {
    $res = db()->query("SELECT user_id, COALESCE(name,'') AS name FROM users WHERE role='owner' ORDER BY user_id DESC LIMIT 200");
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  } catch (Throwable $e) {}
  return $rows;
}

$owners = get_owners();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sample Data Generator</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style> body{padding:24px} .card{max-width:720px;margin:auto} </style>
  </head>
<body>
  <div class="card">
    <div class="card-header">Random Sample Data Generator</div>
    <div class="card-body">
      <p class="text-muted mb-4">Generate random Properties and Rooms for testing. Images will be copied from the local sample images folder.</p>

      <div class="mb-4">
        <h6 class="mb-3">Generate Properties</h6>
        <form class="row g-3" method="post" action="insert_property.php">
          <div class="col-md-4">
            <label class="form-label">Count</label>
            <input type="number" class="form-control" name="count" min="1" max="200" value="10" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Owner (optional)</label>
            <select name="owner_id" class="form-select">
              <option value="">Random owner</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?php echo (int)$o['user_id']; ?>"><?php echo (int)$o['user_id']; ?> - <?php echo htmlspecialchars($o['name'] ?: ('Owner #' . $o['user_id'])); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Initial Status</label>
            <select name="status" class="form-select">
              <option value="pending" selected>pending</option>
              <option value="available">available</option>
              <option value="unavailable">unavailable</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Generate Properties</button>
          </div>
        </form>
      </div>

      <hr>

      <div class="mt-4">
        <h6 class="mb-3">Generate Rooms</h6>
        <form class="row g-3" method="post" action="insert_rooms.php">
          <div class="col-md-4">
            <label class="form-label">Count</label>
            <input type="number" class="form-control" name="count" min="1" max="200" value="10" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Owner (optional)</label>
            <select name="owner_id" class="form-select">
              <option value="">Random owner</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?php echo (int)$o['user_id']; ?>"><?php echo (int)$o['user_id']; ?> - <?php echo htmlspecialchars($o['name'] ?: ('Owner #' . $o['user_id'])); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Initial Status</label>
            <select name="status" class="form-select">
              <option value="pending" selected>pending</option>
              <option value="available">available</option>
              <option value="unavailable">unavailable</option>
            </select>
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit">Generate Rooms</button>
          </div>
        </form>
      </div>

      <hr>
      <div class="mt-3">
        <form method="post" action="delete_all.php" onsubmit="return confirm('Delete ALL sample data (rooms, properties, images)? This cannot be undone.');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ($_SESSION['csrf_token']=bin2hex(random_bytes(16)))); ?>">
          <button type="submit" class="btn btn-outline-danger">Delete All Sample Data</button>
        </form>
      </div>

      <div class="mt-4 small text-muted">
        <div>Uploads:</div>
        <div>Properties images: /uploads/properties</div>
        <div>Rooms images: /uploads/rooms</div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

