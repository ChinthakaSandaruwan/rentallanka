<?php 
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type', 'text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type', 'text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once __DIR__ . '/../public/includes/auth_guard.php'; 
require_role('owner'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Inter Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    /* ===========================
       OWNER DASHBOARD CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-shadow-lg: 0 10px 30px rgba(0,0,0,.15);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
      max-width: 100%;
      overflow-x: hidden;
    }
    
    /* Dashboard Header */
    .rl-dashboard-header {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border-radius: var(--rl-radius-lg);
      padding: 2rem;
      margin-bottom: 2.5rem;
      box-shadow: var(--rl-shadow-md);
      position: relative;
      overflow: hidden;
    }
    
    .rl-dashboard-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      z-index: 0;
    }
    
    .rl-dashboard-header::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -5%;
      width: 200px;
      height: 200px;
      background: rgba(255, 103, 0, 0.15);
      border-radius: 50%;
      z-index: 0;
    }
    
    .rl-dashboard-title {
      font-size: clamp(1.75rem, 3vw, 2.25rem);
      font-weight: 800;
      color: var(--rl-white);
      margin: 0;
      text-align: center;
      position: relative;
      z-index: 1;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .rl-dashboard-subtitle {
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
      margin-top: 0.5rem;
      font-size: 1rem;
      position: relative;
      z-index: 1;
    }
    
    /* Section Headers */
    .rl-section-header {
      margin-top: 3rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .rl-section-header:first-of-type {
      margin-top: 0;
    }
    
    .rl-section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--rl-text);
      margin: 0;
      position: relative;
      padding-left: 1rem;
    }
    
    .rl-section-title::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 70%;
      background: linear-gradient(180deg, var(--rl-dark) 0%, var(--rl-primary) 100%);
      border-radius: 2px;
    }
    
    /* Action Cards */
    .rl-action-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius);
      border: 2px solid var(--rl-border);
      box-shadow: var(--rl-shadow-sm);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
      position: relative;
      height: 100%;
      min-width: 0;
    }
    
    .rl-action-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .rl-action-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--rl-shadow-lg);
      border-color: var(--rl-accent);
    }
    
    .rl-action-card:hover::before {
      opacity: 1;
    }
    
    .rl-card-body {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      text-align: center;
      min-height: 240px;
      height: 100%;
    }
    
    .rl-card-icon {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      transition: transform 0.3s ease;
    }
    
    .rl-action-card:hover .rl-card-icon {
      transform: scale(1.1);
    }
    
    /* Icon Colors */
    .rl-icon-primary { color: var(--rl-primary); }
    .rl-icon-success { color: #10b981; }
    .rl-icon-warning { color: #f59e0b; }
    .rl-icon-danger { color: #ef4444; }
    .rl-icon-info { color: var(--rl-accent); }
    .rl-icon-secondary { color: #6b7280; }
    .rl-icon-orange { color: var(--rl-dark); }
    
    .rl-card-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-text);
      margin-bottom: 0.5rem;
      line-height: 1.4;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    
    .rl-card-text {
      font-size: 0.875rem;
      color: var(--rl-text-muted);
      line-height: 1.6;
      margin-bottom: 1.25rem;
      min-height: 3rem;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    
    /* Action Buttons */
    .rl-btn {
      border-radius: 8px;
      font-weight: 600;
      padding: 0.625rem 1.5rem;
      transition: all 0.2s ease;
      font-size: 0.9375rem;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      text-decoration: none;
    }
    
    .rl-btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.3);
      color: var(--rl-white);
    }
    
    .rl-btn-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-success:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
      color: var(--rl-white);
    }
    
    .rl-btn-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-warning:hover {
      background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
      color: var(--rl-white);
    }
    
    .rl-btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-danger:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
      color: var(--rl-white);
    }
    
    .rl-btn-info {
      background: linear-gradient(135deg, var(--rl-accent) 0%, var(--rl-primary) 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-info:hover {
      background: linear-gradient(135deg, #2d5a8f 0%, #003a75 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(58, 110, 165, 0.3);
      color: var(--rl-white);
    }
    
    .rl-btn-secondary {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      color: var(--rl-white);
    }
    
    .rl-btn-secondary:hover {
      background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
      color: var(--rl-white);
    }
    
    /* Grid Spacing */
    .rl-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
      align-items: stretch;
      max-width: 100%;
    }
    
    .rl-grid:last-of-type {
      margin-bottom: 3rem;
    }
    
    /* Animation */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .rl-dashboard-header,
    .rl-action-card {
      animation: fadeInUp 0.5s ease-out;
    }
    
    .rl-action-card:nth-child(1) { animation-delay: 0.05s; }
    .rl-action-card:nth-child(2) { animation-delay: 0.1s; }
    .rl-action-card:nth-child(3) { animation-delay: 0.15s; }
    .rl-action-card:nth-child(4) { animation-delay: 0.2s; }
    .rl-action-card:nth-child(5) { animation-delay: 0.25s; }
    .rl-action-card:nth-child(6) { animation-delay: 0.3s; }
    
    /* Responsive Design */
    @media (max-width: 991px) {
      .rl-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
    }
    
    @media (max-width: 767px) {
      .rl-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }
    }
    
    @media (max-width: 575px) {
      .rl-grid {
        grid-template-columns: 1fr;
      }
      
      .rl-dashboard-header {
        padding: 1.5rem 1rem;
      }
      
      .rl-dashboard-title {
        font-size: 1.5rem;
      }
      
      .rl-section-title {
        font-size: 1.25rem;
      }
      
      .rl-card-body {
        padding: 1.25rem;
        min-height: 220px;
      }
      
      .rl-card-icon {
        font-size: 2rem;
      }
      
      .rl-card-text {
        min-height: 2.5rem;
      }
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container rl-container">

    <!-- DASHBOARD HEADER -->
    <div class="rl-dashboard-header">
      <h1 class="rl-dashboard-title"><i class="bi bi-speedometer2"></i> Owner Dashboard</h1>
      <p class="rl-dashboard-subtitle">Manage your properties, rooms, and packages</p>
    </div>

    <!-- PROPERTY SECTION -->
    <div class="rl-section-header">
      <h2 class="rl-section-title">Properties</h2>
    </div>
    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-building-add rl-card-icon rl-icon-primary"></i>
          <h5 class="rl-card-title">Create Property</h5>
          <p class="rl-card-text">Add new property listings to your portfolio</p>
          <a href="property/property_create.php" class="rl-btn rl-btn-primary">Create Property</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-eye rl-card-icon rl-icon-success"></i>
          <h5 class="rl-card-title">Read Property</h5>
          <p class="rl-card-text">View and browse all your properties</p>
          <a href="property/property_read.php" class="rl-btn rl-btn-success">Read Property</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-pencil-square rl-card-icon rl-icon-warning"></i>
          <h5 class="rl-card-title">Update Property</h5>
          <p class="rl-card-text">Modify details of existing properties</p>
          <a href="property/property_update.php" class="rl-btn rl-btn-warning">Update Property</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-trash3 rl-card-icon rl-icon-danger"></i>
          <h5 class="rl-card-title">Delete Property</h5>
          <p class="rl-card-text">Remove properties from your listings</p>
          <a href="property/property_delete.php" class="rl-btn rl-btn-danger">Delete Property</a>
        </div>
      </div>
    </div>

    <!-- Property Tools -->
    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-clipboard-check rl-card-icon rl-icon-primary"></i>
          <h5 class="rl-card-title">Property Rent Approvals</h5>
          <p class="rl-card-text">Approve or decline pending property rent requests</p>
          <a href="property/property_rent_approval.php" class="rl-btn rl-btn-primary">Open</a>
        </div>
      </div>
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-toggle2-on rl-card-icon rl-icon-secondary"></i>
          <h5 class="rl-card-title">Property Status</h5>
          <p class="rl-card-text">Toggle property availability status</p>
          <a href="property/property_status.php" class="rl-btn rl-btn-secondary">Open</a>
        </div>
      </div>
    </div>

    <!-- ROOMS SECTION -->
    <div class="rl-section-header">
      <h2 class="rl-section-title">Rooms</h2>
    </div>
    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-door-open rl-card-icon rl-icon-info"></i>
          <h5 class="rl-card-title">Create Room</h5>
          <p class="rl-card-text">Add new room listings to rent out</p>
          <a href="room/room_create.php" class="rl-btn rl-btn-info">Create Room</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-eye rl-card-icon rl-icon-secondary"></i>
          <h5 class="rl-card-title">Read Room</h5>
          <p class="rl-card-text">View and browse all your room listings</p>
          <a href="room/room_read.php" class="rl-btn rl-btn-secondary">Read Room</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-pencil-square rl-card-icon rl-icon-warning"></i>
          <h5 class="rl-card-title">Update Room</h5>
          <p class="rl-card-text">Modify room details and pricing</p>
          <a href="room/room_update.php" class="rl-btn rl-btn-warning">Update Room</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-trash rl-card-icon rl-icon-danger"></i>
          <h5 class="rl-card-title">Delete Room</h5>
          <p class="rl-card-text">Remove rooms from your listings</p>
          <a href="room/room_delete.php" class="rl-btn rl-btn-danger">Delete Room</a>
        </div>
      </div>
    </div>

    <!-- Room Tools -->
    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-calendar-check rl-card-icon rl-icon-success"></i>
          <h5 class="rl-card-title">Rented Rooms</h5>
          <p class="rl-card-text">View currently rented room bookings</p>
          <a href="room/room_rented.php" class="rl-btn rl-btn-success">Open</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-toggle2-on rl-card-icon rl-icon-secondary"></i>
          <h5 class="rl-card-title">Room Status</h5>
          <p class="rl-card-text">Toggle room availability status</p>
          <a href="room/room_status.php" class="rl-btn rl-btn-secondary">Open</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-clipboard-check rl-card-icon rl-icon-primary"></i>
          <h5 class="rl-card-title">Room Approvals</h5>
          <p class="rl-card-text">Approve or decline room bookings</p>
          <a href="room/room_rent_approval.php" class="rl-btn rl-btn-primary">Open</a>
        </div>
      </div>
    </div>


    <!-- PACKAGES SECTION -->
    <div class="rl-section-header">
      <h2 class="rl-section-title">Packages</h2>
    </div>
    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-bag-plus rl-card-icon rl-icon-orange"></i>
          <h5 class="rl-card-title">Buy Package</h5>
          <p class="rl-card-text">Purchase advertising packages to boost visibility</p>
          <a href="package/buy_advertising_packages.php" class="rl-btn rl-btn-primary">Buy Package</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-bag-check rl-card-icon rl-icon-success"></i>
          <h5 class="rl-card-title">Bought Packages</h5>
          <p class="rl-card-text">View and manage your purchased packages</p>
          <a href="package/bought_advertising_packages.php" class="rl-btn rl-btn-success">View Packages</a>
        </div>
      </div>
    </div>



  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
