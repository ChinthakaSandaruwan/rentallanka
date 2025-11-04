<?php
if ((function_exists('session_status') ? session_status() : PHP_SESSION_NONE) === PHP_SESSION_NONE) {
    session_start();
}

// Example session structure
// $_SESSION['loggedin'] = true;
// $_SESSION['role'] = 'admin'; // or 'owner', 'customer'

$isSuper = isset($_SESSION['super_admin_id']);
$loggedIn = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $isSuper;
$role = $_SESSION['role'] ?? '';
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rentallanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .theme-toggle {
      border: none;
      background: none;
      cursor: pointer;
      font-size: 1.3rem;
    }
    .auth-btn {
      margin-left: 10px;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="<?= $base_url ?>/">🏠 Rentallanka</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <!-- Navigation Links -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" href="<?= $base_url ?>/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>/properties.php">Properties</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>/about.php">About</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>/contact.php">Contact</a></li>

          <?php if ($loggedIn): ?>
            <?php if ($isSuper): ?>
              <li class="nav-item"><a class="nav-link text-danger" href="<?= $base_url ?>/superAdmin/index.php">Super Admin Dashboard</a></li>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
              <li class="nav-item"><a class="nav-link text-danger" href="<?= $base_url ?>/admin/index.php">Admin Dashboard</a></li>
            <?php elseif ($role === 'owner'): ?>
              <li class="nav-item"><a class="nav-link text-success" href="<?= $base_url ?>/owner/index.php">Owner Dashboard</a></li>
            <?php elseif ($role === 'customer'): ?>
              <li class="nav-item"><a class="nav-link text-primary" href="<?= $base_url ?>/user/index.php">Customer Dashboard</a></li>
            <?php endif; ?>
            <!-- <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>/public/includes/profile.php">Profile</a></li> -->
          <?php endif; ?>
        </ul>


        <!-- Search Bar -->
        <form class="d-flex me-3" role="search">
          <input class="form-control me-2" type="search" placeholder="Search properties..." aria-label="Search">
          <button class="btn btn-outline-success" type="submit">Search</button>
        </form>

        <!-- Theme Toggle -->
        <button id="themeToggle" class="theme-toggle" title="Toggle theme">
          🌙
        </button>

        <!-- Authentication Buttons -->
        <?php if (!$loggedIn): ?>
          <a href="<?= $base_url ?>/auth/login.php" class="btn btn-outline-primary auth-btn">Login</a>
        <?php else: ?>
          <a href="<?= $base_url ?>/public/includes/profile.php" class="btn btn-outline-secondary auth-btn">Profile</a>
          <a href="<?= $base_url ?>/auth/logout.php" class="btn btn-outline-danger auth-btn">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    htmlElement.setAttribute('data-bs-theme', savedTheme);
    themeToggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

    themeToggle.addEventListener('click', () => {
      const currentTheme = htmlElement.getAttribute('data-bs-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      htmlElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      themeToggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';
    });
  </script>
</body>
</html>

