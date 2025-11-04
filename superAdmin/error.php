<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../config/config.php';

$logFile = __DIR__ . '/../error/error.log';
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'clear') {
        if (file_exists($logFile)) {
            // truncate the file
            $fp = @fopen($logFile, 'w');
            if ($fp) { fclose($fp); $message = 'Log cleared'; } else { $error = 'Unable to clear log'; }
        } else {
            $message = 'Log already empty';
        }
    }
}

// Read (tail) the log content safely (up to ~1MB)
$content = '';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $max = 1024 * 1024; // 1MB
    $offset = max(0, $size - $max);
    $fh = fopen($logFile, 'r');
    if ($fh) {
        if ($offset > 0) { fseek($fh, $offset); }
        $content = stream_get_contents($fh) ?: '';
        fclose($fh);
        if ($offset > 0) { $content = "[...truncated...]\n" . $content; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error Log Viewer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    pre.log { max-height: 70vh; overflow:auto; background:#0f172a; color:#e2e8f0; padding:1rem; border-radius:.5rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Error Log Viewer</h1>
    <div class="d-flex gap-2">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="clear" />
        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Clear error log?')">Clear Log</button>
      </form>
      <a href="error.php" class="btn btn-outline-primary btn-sm">Refresh</a>
    </div>
  </div>

  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-body">
      <?php if ($content !== ''): ?>
        <pre class="log"><?php echo htmlspecialchars($content); ?></pre>
      <?php else: ?>
        <div class="text-muted">No errors logged yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
