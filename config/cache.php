<?php
function app_cache_dir() {
  $dir = __DIR__ . '/../uploads/cache';
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  return realpath($dir) ?: $dir;
}
function app_cache_key($key) {
  return hash('sha256', $key);
}
function app_cache_redis() {
  static $r = null; static $tried = false;
  if ($tried) return $r;
  $tried = true;
  if (class_exists('Redis')) {
    try {
      $host = getenv('REDIS_HOST') ?: '127.0.0.1';
      $port = (int)(getenv('REDIS_PORT') ?: 6379);
      $auth = getenv('REDIS_AUTH') ?: '';
      $db = (int)(getenv('REDIS_DB') ?: 0);
      $client = new Redis();
      if (@$client->connect($host, $port, 0.2)) {
        if ($auth !== '') { @$client->auth($auth); }
        @$client->select($db);
        $r = $client;
      }
    } catch (Throwable $e) { $r = null; }
  }
  return $r;
}
function app_cache_get($key, $ttlSeconds) {
  $rk = app_cache_key($key);
  $r = app_cache_redis();
  if ($r) {
    $raw = @$r->get($rk);
    if ($raw === false || $raw === null) return null;
    $data = @unserialize($raw);
    return $data === false ? null : $data;
  }
  $f = app_cache_dir() . '/' . $rk . '.cache';
  if (!is_file($f)) return null;
  $st = @stat($f);
  if (!$st) return null;
  if (time() - (int)$st['mtime'] > (int)$ttlSeconds) return null;
  $raw = @file_get_contents($f);
  if ($raw === false) return null;
  $data = @unserialize($raw);
  return $data === false ? null : $data;
}
function app_cache_set($key, $value) {
  $rk = app_cache_key($key);
  $r = app_cache_redis();
  if ($r) {
    @$r->set($rk, serialize($value));
    return;
  }
  $f = app_cache_dir() . '/' . $rk . '.cache';
  @file_put_contents($f, serialize($value), LOCK_EX);
}
function app_cache_delete($key) {
  $rk = app_cache_key($key);
  $r = app_cache_redis();
  if ($r) { try { @$r->del($rk); } catch (Throwable $e) {} }
  $f = app_cache_dir() . '/' . $rk . '.cache';
  if (is_file($f)) { @unlink($f); }
}

// Namespace versioning for broad invalidation
function app_cache_ns_version(string $ns): string {
  $r = app_cache_redis();
  $rk = 'ns:' . $ns;
  if ($r) {
    $v = @$r->get($rk);
    if ($v === false || $v === null) { @$r->set($rk, '1'); return '1'; }
    return (string)$v;
  }
  $vf = app_cache_dir() . '/ns_' . preg_replace('/[^a-zA-Z0-9_\-]/','_', $ns) . '.ver';
  if (!is_file($vf)) { @file_put_contents($vf, '1'); return '1'; }
  $v = @trim((string)@file_get_contents($vf));
  return $v !== '' ? $v : '1';
}
function app_cache_bump_ns(string $ns): string {
  $r = app_cache_redis();
  $rk = 'ns:' . $ns;
  if ($r) {
    try { $v = (string)@$r->incr($rk); return $v; } catch (Throwable $e) {}
  }
  $vf = app_cache_dir() . '/ns_' . preg_replace('/[^a-zA-Z0-9_\-]/','_', $ns) . '.ver';
  $cur = (int)app_cache_ns_version($ns);
  $new = (string)($cur + 1);
  @file_put_contents($vf, $new, LOCK_EX);
  return $new;
}
function app_cache_ns_key(string $ns, string $key): string {
  $ver = app_cache_ns_version($ns);
  return $ns . ':v' . $ver . ':' . $key;
}
