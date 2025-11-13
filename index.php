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
    <!-- Preconnect to CDNs for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://images.pexels.com" crossorigin>
    <link rel="dns-prefetch" href="https://images.pexels.com">
    
    <!-- Critical CSS loaded synchronously -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    
    <!-- Non-critical CSS loaded asynchronously -->
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></noscript>
    
    <!-- Mobile Overflow Fix -->
    <link href="<?php echo $base_url; ?>/public/assets/css/mobile-fix.css" rel="stylesheet">
    <style>
      /* Page preloader */
      #rl-preloader { position: fixed; inset: 0; background: #ffffff; z-index: 2000; display: flex; align-items: center; justify-content: center; transition: opacity .25s ease, visibility .25s ease; }
      #rl-preloader .rl-loader { width: 54px; height: 54px; border-radius: 50%; border: 4px solid #e5e7eb; border-top-color: #004E98; animation: rl-spin .9s linear infinite; }
      @keyframes rl-spin { to { transform: rotate(360deg); } }
      .rl-preloader-hide { opacity: 0; visibility: hidden; }
    </style>
    <script>
      window.addEventListener('load', function() {
        const preloader = document.getElementById('rl-preloader');
        preloader.classList.add('rl-preloader-hide');
        setTimeout(function() {
          preloader.remove();
        }, 250);
      });
    </script>
  </head>
  <body>
    <div id="rl-preloader" aria-hidden="true"><div class="rl-loader" role="status" aria-label="Loading"></div></div>
    <?php include 'public/includes/navbar.php'; ?>
    
    <!-- Hero Section (Full Width) -->
    <?php include 'public/includes/hero.php'; ?>
    
    <!-- Main Content Container -->
    <div class="container my-4">
      <!-- Search Section -->
      <?php include 'public/includes/search.php'; ?>
      
      <hr class="my-4">
      
      <!-- Properties Section -->
      <?php include 'public/includes/property.php'; ?>
      
      <hr class="my-4">
      
      <!-- Rooms Section -->
      <?php include 'public/includes/room.php'; ?>
    </div>
    
    <?php include 'public/includes/footer.php'; ?>

    <!-- Bootstrap JS must load synchronously first -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Force dropdown initialization after Bootstrap is loaded -->
    <script>
      (function() {
        // Wait for Bootstrap to be fully loaded
        var checkBootstrap = setInterval(function() {
          if (typeof bootstrap !== 'undefined') {
            clearInterval(checkBootstrap);
            
            // Initialize all dropdowns
            var dropdownElementList = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            dropdownElementList.forEach(function(dropdownToggleEl) {
              new bootstrap.Dropdown(dropdownToggleEl, {
                autoClose: true
              });
            });
            
            console.log('Dropdowns initialized:', dropdownElementList.length);
          }
        }, 50);
        
        // Timeout after 5 seconds
        setTimeout(function() {
          clearInterval(checkBootstrap);
        }, 5000);
      })();
    </script>
    
    <!-- Performance optimization script (loads after Bootstrap) -->
    <script src="<?php echo $base_url; ?>/public/assets/js/performance.js"></script>
  </body>
</html>
