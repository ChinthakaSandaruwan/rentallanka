<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ___DIR___ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = ___DIR___ . '/error.log';
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
// Usage: set $seo = [ 'title'=>..., 'description'=>..., 'url'=>..., 'image'=>..., 'type'=>'website'|'article'|'product' ];
$seo = $seo ?? [];
$base = rtrim($GLOBALS['base_url'] ?? '', '/');
$title = htmlspecialchars((string)($seo['title'] ?? 'Rentallanka'), ENT_QUOTES, 'UTF-8');
$desc  = htmlspecialchars((string)($seo['description'] ?? 'Find properties and rooms for rent across Sri Lanka.'), ENT_QUOTES, 'UTF-8');
$url   = htmlspecialchars((string)($seo['url'] ?? ($base !== '' ? $base . '/' : '/')), ENT_QUOTES, 'UTF-8');
$image = htmlspecialchars((string)($seo['image'] ?? ''), ENT_QUOTES, 'UTF-8');
$type  = htmlspecialchars((string)($seo['type'] ?? 'website'), ENT_QUOTES, 'UTF-8');
?>
<title><?= $title ?></title>
<meta name="description" content="<?= $desc ?>">
<link rel="canonical" href="<?= $url ?>">
<meta property="og:title" content="<?= $title ?>">
<meta property="og:description" content="<?= $desc ?>">
<meta property="og:type" content="<?= $type ?>">
<meta property="og:url" content="<?= $url ?>">
<?php if ($image !== ''): ?>
<meta property="og:image" content="<?= $image ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $title ?>">
<meta name="twitter:description" content="<?= $desc ?>">
<?php if ($image !== ''): ?>
<meta name="twitter:image" content="<?= $image ?>">
<?php endif; ?>
