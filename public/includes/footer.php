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
require_once __DIR__ . '/../../config/config.php';
$get = function(string $k, string $d=''){ $v = setting_get($k, $d); return ($v===null)?$d:$v; };
$show_social = $get('footer_show_social','1') === '1';
$show_products = $get('footer_show_products','1') === '1';
$show_useful = $get('footer_show_useful_links','1') === '1';
$show_contact = $get('footer_show_contact','1') === '1';
$company = $get('footer_company_name','Company name');
$about = $get('footer_about','Here you can use rows and columns to organize your footer content.');
$addr = $get('footer_address','New York, NY 10012, US');
$email = $get('footer_email','info@example.com');
$phone = $get('footer_phone','+ 01 234 567 88');
$links_products = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$get('footer_products_links','Angular|#\nReact|#\nVue|#\nLaravel|#'))));
$links_useful = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$get('footer_useful_links','Pricing|#\nSettings|#\nOrders|#\nHelp|#'))));
$social = [
  ['k'=>'footer_social_facebook','icon'=>'bi-facebook'],
  ['k'=>'footer_social_twitter','icon'=>'bi-twitter-x'],
  ['k'=>'footer_social_google','icon'=>'bi-google'],
  ['k'=>'footer_social_instagram','icon'=>'bi-instagram'],
  ['k'=>'footer_social_linkedin','icon'=>'bi-linkedin'],
  ['k'=>'footer_social_github','icon'=>'bi-github'],
];
// Normalize URLs to include base_url when not absolute
$base = rtrim($GLOBALS['base_url'] ?? '', '/');
$resolve = function(string $u) use ($base): string {
  $u = trim($u);
  if ($u === '') return '#';
  if (preg_match('#^https?://#i', $u)) return $u;
  return $base . '/' . ltrim($u, '/');
};
?>
<style>
  /* ===========================
     RENTALLANKA FOOTER THEME
     Matches navbar/sections tokens
     =========================== */
  :root {
    --rl-primary: #004E98;
    --rl-light: #EBEBEB;
    --rl-secondary: #C0C0C0;
    --rl-accent: #3A6EA5;
    --rl-dark: #FF6700;
    --rl-bg: #ffffff;
    --rl-text: #1f2a37;
    --rl-muted: #6b7280;
    --rl-border: #E5E7EB;
    --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
  }

  .rl-footer { background: var(--rl-bg); color: var(--rl-muted); border-top: 1px solid var(--rl-border); box-shadow: var(--rl-shadow-sm); }
  .rl-footer .rl-wrap { padding: clamp(1.5rem, 2vw, 2.5rem) 0; }
  .rl-footer h6 { color: var(--rl-primary); font-weight: 800; letter-spacing: .2px; }
  .rl-footer p { color: var(--rl-text); }
  .rl-footer a { color: var(--rl-text); text-decoration: none; }
  .rl-footer a:hover { color: var(--rl-primary); text-decoration: none; }
  .rl-footer .rl-social a { color: var(--rl-text); margin-right: .75rem; }
  .rl-footer .rl-social a:hover { color: var(--rl-primary); }
  .rl-footer .rl-copy { background: #f8fafc; border-top: 1px solid var(--rl-border); color: var(--rl-muted); }
  @media (max-width: 575px) { .rl-footer .rl-wrap { padding: 1.25rem 0; } }
</style>
<!-- Bootstrap Icons CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- Footer -->
<footer class="rl-footer text-center text-lg-start text-muted">
  <!-- Section: Social media -->
  <?php if ($show_social): ?>
  <section class="d-flex justify-content-center justify-content-lg-between p-4 border-bottom rl-wrap">
    <!-- Left -->
    <div class="me-5 d-none d-lg-block">
      <span>Get connected with us on social networks:</span>
    </div>
    <!-- Left -->

    <!-- Right -->
   <div class="rl-social">
  <?php foreach ($social as $s): $url = trim($get($s['k'],'')); if ($url==='') continue; ?>
  <a href="<?= htmlspecialchars($url) ?>" class="me-4 text-reset text-decoration-none">
    <i class="bi <?= htmlspecialchars($s['icon']) ?>"></i>
  </a>
  <?php endforeach; ?>
</div>

    <!-- Right -->
  </section>
  <?php endif; ?>
  <!-- Section: Social media -->

  <!-- Section: Links  -->
  <section class="rl-wrap">
    <div class="container text-center text-md-start">
      <!-- Grid row -->
      <div class="row g-4">
        <!-- Grid column -->
        <div class="col-md-3 col-lg-4 col-xl-3 mx-auto mb-4">
          <!-- Content -->
          <h6 class="text-uppercase fw-bold mb-4">
            <i class="bi bi-gem me-3"></i><?= htmlspecialchars($company) ?>
          </h6>
          <p>
            <?= htmlspecialchars($about) ?>
          </p>
        </div>
        <!-- Grid column -->

        <!-- Grid column -->
        <?php if ($show_products): ?>
        <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mb-4">
          <!-- Links -->
          <h6 class="text-uppercase fw-bold mb-4">Products</h6>
          <?php foreach ($links_products as $line): $parts = explode('|', $line, 2); $label=trim($parts[0]??''); $url=$resolve((string)($parts[1]??'#')); if ($label==='') continue; ?>
          <p><a href="<?= htmlspecialchars($url) ?>" class="text-reset text-decoration-none"><?= htmlspecialchars($label) ?></a></p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Grid column -->

        <!-- Grid column -->
        <?php if ($show_useful): ?>
        <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mb-4">
          <!-- Links -->
          <h6 class="text-uppercase fw-bold mb-4">Useful links</h6>
          <?php foreach ($links_useful as $line): $parts = explode('|', $line, 2); $label=trim($parts[0]??''); $url=$resolve((string)($parts[1]??'#')); if ($label==='') continue; ?>
          <p><a href="<?= htmlspecialchars($url) ?>" class="text-reset text-decoration-none"><?= htmlspecialchars($label) ?></a></p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Grid column -->

        <!-- Grid column -->
        <?php if ($show_contact): ?>
        <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mb-md-0 mb-4">
          <!-- Links -->
          <h6 class="text-uppercase fw-bold mb-4">Contact</h6>
          <p><i class="bi bi-geo-alt me-3"></i> <?= htmlspecialchars($addr) ?></p>
          <p><i class="bi bi-envelope me-3"></i> <?= htmlspecialchars($email) ?></p>
          <p><i class="bi bi-telephone me-3"></i> <?= htmlspecialchars($phone) ?></p>
          <p><i class="bi bi-printer me-3"></i> </p>
        </div>
        <?php endif; ?>
        <!-- Grid column -->
      </div>
      <!-- Grid row -->
    </div>
  </section>
  <!-- Section: Links  -->

  <!-- Copyright -->
  <div class="text-center p-4 rl-copy">
    <?= htmlspecialchars($get('footer_copyright_text','&copy; '.date('Y').' Copyright:')) ?>
    <a class="text-reset text-decoration-none fw-bold" href="<?= htmlspecialchars($base_url) ?>"><?= htmlspecialchars($company) ?></a>
  </div>
  <!-- Copyright -->
</footer>
<!-- Footer -->
<?php
