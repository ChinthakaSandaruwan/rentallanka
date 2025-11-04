<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('owner');

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price_per_month = (float)($_POST['price_per_month'] ?? 0);
        $bedrooms = (int)($_POST['bedrooms'] ?? 0);
        $bathrooms = (int)($_POST['bathrooms'] ?? 0);
        $living_rooms = (int)($_POST['living_rooms'] ?? 0);
        $has_kitchen = isset($_POST['has_kitchen']) ? 1 : 0;
        $has_parking = isset($_POST['has_parking']) ? 1 : 0;
        $has_water_supply = isset($_POST['has_water_supply']) ? 1 : 0;
        $has_electricity_supply = isset($_POST['has_electricity_supply']) ? 1 : 0;
        $allowed_types = ['house','apartment','room','commercial','other'];
        $property_type = $_POST['property_type'] ?? 'other';
        if (!in_array($property_type, $allowed_types, true)) {
            $property_type = 'other';
        }

        if ($title === '' || $price_per_month < 0) {
            $error = 'Title and price are required';
        } else {
            $stmt = db()->prepare('INSERT INTO properties (owner_id, title, description, price_per_month, bedrooms, bathrooms, living_rooms, has_kitchen, has_parking, has_water_supply, has_electricity_supply, property_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param(
                'issdiiiiiiis',
                $uid,
                $title,
                $description,
                $price_per_month,
                $bedrooms,
                $bathrooms,
                $living_rooms,
                $has_kitchen,
                $has_parking,
                $has_water_supply,
                $has_electricity_supply,
                $property_type
            );
            if ($stmt->execute()) {
                $stmt->close();
                redirect_with_message($GLOBALS['base_url'] . '/owner/property_management.php', 'Property submitted. Awaiting admin approval.', 'success');
            } else {
                $error = 'Failed to create property';
                $stmt->close();
            }
        }
    }
}

$props = [];
$stmt = db()->prepare('SELECT property_id, title, status, created_at, price_per_month FROM properties WHERE owner_id = ? ORDER BY property_id DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $props[] = $row;
}
$stmt->close();
[$flash, $flash_type] = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Property Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  </head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Property Management</h1>
  </div>
  <?php if ($flash): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <div class="row g-4">
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header">Add Property</div>
        <div class="card-body">
          <form method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" rows="3" class="form-control"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Price per month (LKR)</label>
              <input name="price_per_month" type="number" min="0" step="0.01" class="form-control" required>
            </div>
            <div class="row g-3 mb-3">
              <div class="col">
                <label class="form-label">Bedrooms</label>
                <input name="bedrooms" type="number" min="0" value="0" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Bathrooms</label>
                <input name="bathrooms" type="number" min="0" value="0" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Living rooms</label>
                <input name="living_rooms" type="number" min="0" value="0" class="form-control">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_kitchen" id="has_kitchen">
                  <label class="form-check-label" for="has_kitchen">Kitchen</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_parking" id="has_parking">
                  <label class="form-check-label" for="has_parking">Parking</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_water_supply" id="has_water_supply">
                  <label class="form-check-label" for="has_water_supply">Water</label>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="has_electricity_supply" id="has_electricity_supply">
                  <label class="form-check-label" for="has_electricity_supply">Electricity</label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Property type</label>
              <select name="property_type" class="form-select">
                <option value="house">House</option>
                <option value="apartment">Apartment</option>
                <option value="room">Room</option>
                <option value="commercial">Commercial</option>
                <option value="other">Other</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Submit for Approval</button>
            <p class="text-muted mt-2 mb-0"><small>Status will be pending until admin approves.</small></p>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header">Your Properties</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Title</th>
                  <th>Status</th>
                  <th>Price/mo</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($props as $p): ?>
                  <tr>
                    <td><?php echo (int)$p['property_id']; ?></td>
                    <td><?php echo htmlspecialchars($p['title']); ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($p['status']); ?></span></td>
                    <td><?php echo number_format((float)$p['price_per_month'], 2); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$props): ?>
                  <tr><td colspan="5" class="text-center py-4">No properties yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKvVYl0ZlEFp3rG5GkHA7r4XK6tBT3M" crossorigin="anonymous"></script>
</body>
</html>
