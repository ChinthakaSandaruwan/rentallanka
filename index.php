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
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      require_once __DIR__ . '/config/config.php';
      $seo = [
        'title' => 'Rentallanka – Properties & Rooms for Rent in Sri Lanka',
        'description' => 'Browse properties and rooms for rent across Sri Lanka. Find apartments, houses and rooms with filters and maps.',
        'url' => rtrim($base_url,'/') . '/',
        'type' => 'website'
      ];
      require_once __DIR__ . '/public/includes/seo_meta.php';
    ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://images.pexels.com" crossorigin>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" onload="this.onload=null;this.rel='stylesheet'">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
   
</head>
  <body >


<?php include 'public/includes/navbar.php'; ?>
<?php include 'public/includes/hero.php'; ?>
<hr/>
<?php include 'public/includes/search.php'; ?>

<hr/>
<?php include 'public/includes/property.php'; ?>
<hr/>
<?php include 'public/includes/room.php'; ?>
<hr/>
<?php include 'public/includes/footer.php'; ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  </body>
</html>