<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
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
    // Enforce POST-Redirect-GET
    $self_url = rtrim($base_url, '/') . '/superAdmin/error.php';
    if (!headers_sent()) {
        if (!empty($error)) {
            redirect_with_message($self_url, $error, 'error');
        } elseif (!empty($message)) {
            redirect_with_message($self_url, $message, 'success');
        } else {
            header('Location: ' . $self_url);
            exit;
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
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
      <form method="post" class="d-inline" data-swal="clear-log">
        <input type="hidden" name="action" value="clear" />
        <button type="submit" class="btn btn-outline-danger btn-sm">Clear Log</button>
      </form>
      <a href="error.php" class="btn btn-outline-primary btn-sm">Refresh</a>
    </div>
  </div>

  <?php [$flash, $flash_type] = get_flash(); ?>
  <?php if ($flash): ?><?php endif; ?>

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    var flash = <?php echo json_encode($flash ?? ''); ?>;
    var flashType = <?php echo json_encode($flash_type ?? 'info'); ?>;
    if (flash) {
      Swal.fire({ toast: true, position: 'top-end', icon: (flashType === 'error' ? 'error' : (flashType === 'warning' ? 'warning' : 'success')), title: flash, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }
    var clearForm = document.querySelector('form[data-swal="clear-log"]');
    if (clearForm) {
      clearForm.addEventListener('submit', function(e){
        e.preventDefault();
        Swal.fire({
          title: 'Clear error log?',
          text: 'This will truncate the log file.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, clear',
          cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) clearForm.submit(); });
      });
    }
  });
</script>
</body>
</html>
