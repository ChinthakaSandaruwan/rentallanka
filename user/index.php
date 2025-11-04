<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('customer');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1>👤 Customer Dashboard</h1>
  <p>Welcome to your dashboard.</p>
  <a href="../index.php" class="btn btn-outline-secondary">Back to Home</a>
</body>
</html>
