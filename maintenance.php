<?php
// Simple public maintenance page
// If maintenance flag is removed, send users back to home automatically.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/error.log');
require_once ___DIR___ . '/config/config.php';
$flagFile = ___DIR___ . '/maintain.flag';
if (!is_file($flagFile)) {
  if (!headers_sent()) { header('Location: ' . rtrim($base_url, '/') . '/'); }
  exit;
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>We'll be back soon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-text:#1f2a37; --rl-muted:#6b7280; }
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background: linear-gradient(180deg,#fff 0%, #f3f4f6 100%); font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif; }
    .card { border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,.08); }
    .badge-brand { background: linear-gradient(135deg,var(--rl-primary),var(--rl-accent)); }
    .btn-brand { background: linear-gradient(135deg,var(--rl-primary),var(--rl-accent)); color:#fff; border:none; }
  </style>
</head>
<body>
  <div class="container px-3">
    <div class="card mx-auto" style="max-width:720px;">
      <div class="card-body p-4 p-md-5 text-center">
        <span class="badge badge-brand mb-3">Maintenance</span>
        <h1 class="display-6 fw-bold mb-2" style="color:var(--rl-text)">We’re doing some work</h1>
        <p class="text-muted mb-4">Our site is undergoing scheduled maintenance. We’ll be back as soon as possible.</p>
        <div class="d-flex gap-2 justify-content-center">
          <a class="btn btn-brand" href="/">Go to Home</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
