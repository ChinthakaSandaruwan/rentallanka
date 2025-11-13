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
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/xml; charset=UTF-8');
$base = rtrim($GLOBALS['base_url'] ?? '', '/');
$urls = [];
$now = date('c');
function add_url(&$arr, $loc, $priority = '0.6', $freq = 'daily', $lastmod = null) {
  $arr[] = [
    'loc' => $loc,
    'priority' => $priority,
    'changefreq' => $freq,
    'lastmod' => $lastmod ?: date('c'),
  ];
}
// Core pages
add_url($urls, $base . '/', '1.0', 'daily', $now);
add_url($urls, $base . '/public/includes/all_properties.php', '0.8', 'daily', $now);
add_url($urls, $base . '/public/includes/all_rooms.php', '0.8', 'daily', $now);
add_url($urls, $base . '/public/includes/advance_search.php?type=property', '0.5', 'weekly', $now);
add_url($urls, $base . '/public/includes/advance_search.php?type=room', '0.5', 'weekly', $now);
// Recent properties
try {
  $res = db()->query('SELECT property_id, updated_at, created_at FROM properties WHERE status="available" ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 200');
  while ($r = $res->fetch_assoc()) {
    $lm = $r['updated_at'] ?: $r['created_at'] ?: $now;
    add_url($urls, $base . '/public/includes/view_property.php?id=' . (int)$r['property_id'], '0.7', 'weekly', date('c', strtotime($lm)));
  }
} catch (Throwable $e) {}
// Recent rooms
try {
  $res = db()->query('SELECT room_id, updated_at, created_at FROM rooms WHERE status="available" ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 200');
  while ($r = $res->fetch_assoc()) {
    $lm = $r['updated_at'] ?: $r['created_at'] ?: $now;
    add_url($urls, $base . '/public/includes/view_room.php?id=' . (int)$r['room_id'], '0.6', 'weekly', date('c', strtotime($lm)));
  }
} catch (Throwable $e) {}
// Province landing pages (top 10 by id asc)
try {
  $res = db()->query('SELECT id, name_en FROM provinces ORDER BY id ASC LIMIT 10');
  while ($r = $res->fetch_assoc()) {
    add_url($urls, $base . '/public/locations/province.php?province_id=' . (int)$r['id'], '0.6', 'weekly', $now);
  }
} catch (Throwable $e) {}

// Top cities (first 50 by id) for city landing pages
try {
  $res = db()->query('SELECT id, name_en FROM cities ORDER BY id ASC LIMIT 50');
  while ($r = $res->fetch_assoc()) {
    add_url($urls, $base . '/public/locations/city.php?city_id=' . (int)$r['id'], '0.5', 'weekly', $now);
  }
} catch (Throwable $e) {}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $u) {
  echo "  <url>\n";
  echo "    <loc>" . htmlspecialchars($u['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
  if (!empty($u['lastmod'])) echo "    <lastmod>" . htmlspecialchars($u['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
  if (!empty($u['changefreq'])) echo "    <changefreq>" . htmlspecialchars($u['changefreq'], ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
  if (!empty($u['priority'])) echo "    <priority>" . htmlspecialchars($u['priority'], ENT_QUOTES, 'UTF-8') . "</priority>\n";
  echo "  </url>\n";
}
echo "</urlset>\n";
