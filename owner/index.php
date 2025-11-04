<?php
session_start();
$_SESSION['role'] = 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Owner Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1>🏠 Owner Dashboard</h1>
  <p>Manage your properties and view rental requests.</p>
  <a href="../index.php" class="btn btn-outline-secondary">Back to Home</a>
</body>
</html>
