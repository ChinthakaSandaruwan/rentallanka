<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin');

$slip_id = (int)($_GET['slip_id'] ?? 0);
if ($slip_id <= 0) {
  http_response_code(400);
  echo 'Invalid slip id';
  exit;
}

$sp = db()->prepare('SELECT slip_path, uploaded_at, property_id FROM property_payment_slips WHERE slip_id = ? LIMIT 1');
$sp->bind_param('i', $slip_id);
$sp->execute();
$res = $sp->get_result();
$slip = $res->fetch_assoc();
$sp->close();

if (!$slip) {
  http_response_code(404);
  echo 'Slip not found';
  exit;
}

$src = (string)($slip['slip_path'] ?? '');
if ($src && !preg_match('#^https?://#i', $src) && $src[0] !== '/') { $src = '/' . ltrim($src, '/'); }

// If the user wants to open the slip, redirect directly to the file URL (no in-page preview)
$is_external = preg_match('#^https?://#i', $src);
$openUrl = $is_external ? $src : (rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($src, '/'));
header('Location: ' . $openUrl);
exit;

// Raw streaming mode: always serve inline to avoid download prompts (unused after redirect, kept as fallback)
if (isset($_GET['raw']) && $_GET['raw'] == '1') {
  // Only support local paths (not external URLs) for security
  if (!$src || preg_match('#^https?://#i', $src)) {
    http_response_code(400);
    echo 'Invalid source';
    exit;
  }
  $root = realpath(__DIR__ . '/..'); // project root (rentallanka)
  $full = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $src), DIRECTORY_SEPARATOR));
  if (!$full || strpos($full, $root) !== 0 || !is_file($full)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }
  // Detect mime
  $mime = 'application/octet-stream';
  if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) { $det = finfo_file($f, $full); if ($det) { $mime = $det; } @finfo_close($f); }
  } else {
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $map = [
      'pdf' => 'application/pdf',
      'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'
    ];
    if (isset($map[$ext])) { $mime = $map[$ext]; }
  }
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($full));
  header('Content-Disposition: inline; filename="' . basename($full) . '"');
  header('X-Content-Type-Options: nosniff');
  readfile($full);
  exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slip #<?php echo (int)$slip_id; ?> | Property #<?php echo (int)($slip['property_id'] ?? 0); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>html,body{height:100%} .viewer{height:calc(100vh - 60px);} iframe,embed{width:100%; height:100%; border:0}</style>
</head>
<body>
<nav class="navbar navbar-light bg-light border-bottom">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h6">Payment Slip #<?php echo (int)$slip_id; ?> (Uploaded: <?php echo htmlspecialchars($slip['uploaded_at'] ?? ''); ?>)</span>
    <a class="btn btn-sm btn-outline-secondary" href="property_view.php?id=<?php echo (int)($slip['property_id'] ?? 0); ?>">Back to property</a>
  </div>
</nav>
<div class="viewer">
  <?php if ($src): ?>
    <?php 
      $ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
      $is_external = preg_match('#^https?://#i', $src);
      $viewerSrc = $is_external ? $src : (rtrim($GLOBALS['base_url'] ?? '', '/') . '/' . ltrim($src, '/'));
    ?>
    <?php if ($ext === 'pdf'): ?>
      <object data="<?php echo htmlspecialchars($viewerSrc); ?>#toolbar=1&navpanes=0&scrollbar=1" type="application/pdf" width="100%" height="100%">
        <div class="container py-4">
          <div class="alert alert-info">
            PDF preview is not available in this browser. 
            <a class="alert-link" href="<?php echo htmlspecialchars($viewerSrc); ?>" target="_blank">Open the PDF directly</a>.
          </div>
        </div>
      </object>
    <?php else: ?>
      <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
        <div class="d-flex align-items-center justify-content-center" style="height:100%">
          <img src="<?php echo htmlspecialchars($viewerSrc); ?>" alt="Slip image" style="max-width:100%; max-height:100%; object-fit:contain;" />
        </div>
      <?php else: ?>
        <iframe src="<?php echo htmlspecialchars($viewerSrc); ?>" title="Slip preview"></iframe>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="container py-4"><div class="alert alert-warning">No slip source found.</div></div>
  <?php endif; ?>
</div>
</body>
</html>
