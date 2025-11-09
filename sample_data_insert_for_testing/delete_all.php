<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
  http_response_code(403);
  echo '<!doctype html><html><body class="p-4"><div style="padding:16px;font-family:sans-serif;color:#b71c1c">Invalid request.</div></body></html>';
  exit;
}

$errors = [];

// Helper to remove files in a directory (non-recursive)
function remove_dir_files(string $dir): void {
  if (!is_dir($dir)) return;
  $h = @opendir($dir);
  if ($h === false) return;
  while (($f = readdir($h)) !== false) {
    if ($f === '.' || $f === '..') continue;
    $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $f;
    if (is_file($p)) { @unlink($p); }
  }
  closedir($h);
}

try {
  // Remove uploaded images from disk
  $roomsUpload = dirname(__DIR__) . '/uploads/rooms';
  $propsUpload = dirname(__DIR__) . '/uploads/properties';
  remove_dir_files($roomsUpload);
  remove_dir_files($propsUpload);

  // Begin DB cleanup
  db()->begin_transaction();

  // Locations (no FKs) referencing rooms/properties
  try { db()->query("DELETE FROM locations WHERE room_id IS NOT NULL"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM locations WHERE property_id IS NOT NULL"); } catch (Throwable $e) {}

  // Room-related tables
  try { db()->query("DELETE FROM room_meal_prices"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM room_wishlist"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM room_rents"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM room_images"); } catch (Throwable $e) {}

  // Property-related tables
  try { db()->query("DELETE FROM property_images"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM reviews"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM wishlist"); } catch (Throwable $e) {}

  // Parents
  try { db()->query("DELETE FROM rooms"); } catch (Throwable $e) {}
  try { db()->query("DELETE FROM properties"); } catch (Throwable $e) {}

  db()->commit();
} catch (Throwable $e) {
  db()->rollback();
  $errors[] = $e->getMessage();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete All Sample Data</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:720px;">
    <div class="alert <?php echo empty($errors) ? 'alert-success' : 'alert-danger'; ?>">
      <?php if (empty($errors)): ?>
        <div class="fw-semibold">All sample data removed.</div>
        <div class="small text-muted">Uploads folders were cleared (non-recursive) and related tables truncated.</div>
      <?php else: ?>
        <div class="fw-semibold">Some errors occurred while deleting.</div>
        <ul class="mb-0 small">
          <?php foreach ($errors as $er): ?><li><?php echo htmlspecialchars($er); ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <a class="btn btn-secondary" href="index.php">Back to Generator</a>
  </div>
</body>
</html>
