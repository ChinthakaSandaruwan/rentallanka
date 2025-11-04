<?php
session_start();
$_SESSION['role'] = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1>🛠️ Admin Dashboard</h1>
  <p>Welcome, Administrator!</p>
  <a href="../index.php" class="btn btn-outline-secondary">Back to Home</a>
</body>
</html>
