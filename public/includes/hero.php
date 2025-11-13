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
?>
<div id="heroCarousel" class="carousel slide carousel-fade rl-hero" data-bs-ride="carousel">
  <!-- dont add buttons and a tags to navigate other pages -->
  <style>
    /* ===========================
       HERO CAROUSEL CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
    }
    
    /* Hero Container */
    .rl-hero {
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    /* Carousel Items */
    .rl-hero .carousel-item {
      position: relative;
      height: 70vh;
      min-height: 500px;
      max-height: 800px;
    }
    
    .rl-hero .hero-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
    }
    
    /* Gradient Overlay */
    .rl-hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(
        135deg,
        rgba(0, 0, 0, 0.55) 0%,
        rgba(0, 0, 0, 0.35) 60%,
        rgba(0, 0, 0, 0.20) 100%
      );
      pointer-events: none;
      z-index: 1;
    }
    
    /* Caption Container */
    .rl-hero .carousel-caption {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      right: auto;
      bottom: auto;
      width: 90%;
      max-width: 1200px;
      z-index: 2;
      text-align: center;
      padding: 2rem;
      animation: none !important;
      transition: none !important;
    }
    
    /* Disable any animations to prevent shifting on load */
    .rl-hero .carousel-item .carousel-caption { animation: none !important; }
    
    /* Hero Title */
    .rl-hero-title {
      font-size: clamp(2rem, 5vw, 3.5rem);
      font-weight: 800;
      color: var(--rl-white);
      margin-bottom: 1rem;
      line-height: 1.2;
      text-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
      letter-spacing: -0.5px;
    }
    
    /* Subtitle */
    .rl-hero-sub {
      font-size: clamp(1rem, 2.5vw, 1.375rem);
      font-weight: 400;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 2rem;
      line-height: 1.6;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }
    
    /* Carousel Indicators */
    .rl-hero .carousel-indicators {
      bottom: 2rem;
      margin-bottom: 0;
      z-index: 3;
    }
    
    .rl-hero .carousel-indicators button {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      border: 2px solid var(--rl-white);
      background-color: transparent;
      opacity: 0.6;
      transition: all 0.3s ease;
      margin: 0 6px;
    }
    
    .rl-hero .carousel-indicators button.active {
      width: 40px;
      border-radius: 6px;
      background: linear-gradient(90deg, var(--rl-dark) 0%, var(--rl-accent) 100%);
      opacity: 1;
      border-color: var(--rl-dark);
    }
    
    .rl-hero .carousel-indicators button:hover {
      opacity: 1;
      transform: scale(1.2);
    }
    
    /* Carousel Controls */
    .rl-hero .carousel-control-prev,
    .rl-hero .carousel-control-next {
      width: 60px;
      height: 60px;
      top: 50%;
      transform: translateY(-50%);
      opacity: 0;
      transition: all 0.3s ease;
      z-index: 3;
    }
    
    .rl-hero:hover .carousel-control-prev,
    .rl-hero:hover .carousel-control-next {
      opacity: 1;
    }
    
    .rl-hero .carousel-control-prev {
      left: 1rem;
    }
    
    .rl-hero .carousel-control-next {
      right: 1rem;
    }
    
    .rl-hero .carousel-control-prev-icon,
    .rl-hero .carousel-control-next-icon {
      width: 50px;
      height: 50px;
      background-color: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 50%;
      border: 2px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }
    
    .rl-hero .carousel-control-prev:hover .carousel-control-prev-icon,
    .rl-hero .carousel-control-next:hover .carousel-control-next-icon {
      background-color: var(--rl-dark);
      border-color: var(--rl-dark);
      transform: scale(1.1);
    }
    
    /* Decorative Elements */
    .rl-hero-badge {
      display: inline-block;
      padding: 0.5rem 1.25rem;
      background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8533 100%);
      color: var(--rl-white);
      font-weight: 700;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-radius: 50px;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 12px rgba(255, 103, 0, 0.3);
      animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.05);
      }
    }
    
    /* Responsive Design */
    @media (max-width: 991px) {
      .rl-hero .carousel-item {
        height: 60vh;
        min-height: 450px;
      }
      
      .rl-hero-title {
        font-size: clamp(1.75rem, 4vw, 2.5rem);
      }
      
      .rl-hero-sub {
        font-size: clamp(0.9375rem, 2vw, 1.125rem);
      }
      
      .rl-hero .carousel-control-prev,
      .rl-hero .carousel-control-next {
        width: 50px;
        height: 50px;
      }
      
      .rl-hero .carousel-control-prev-icon,
      .rl-hero .carousel-control-next-icon {
        width: 40px;
        height: 40px;
      }
    }
    
    @media (max-width: 767px) {
      .rl-hero .carousel-item {
        height: 55vh;
        min-height: 400px;
      }
      
      .rl-hero .carousel-caption {
        padding: 1.5rem 1rem;
      }
      
      .rl-hero-title {
        margin-bottom: 0.75rem;
      }
      
      .rl-hero-sub {
        margin-bottom: 1.5rem;
        font-size: 1rem;
      }
      
      .rl-hero-badge {
        font-size: 0.75rem;
        padding: 0.4rem 1rem;
        margin-bottom: 1rem;
      }
      
      .rl-hero .carousel-indicators {
        bottom: 1rem;
      }
      
      .rl-hero .carousel-indicators button {
        width: 10px;
        height: 10px;
        margin: 0 4px;
      }
      
      .rl-hero .carousel-indicators button.active {
        width: 30px;
      }
    }
    
    @media (max-width: 575px) {
      .rl-hero .carousel-item {
        height: 50vh;
        min-height: 350px;
      }
      
      .rl-hero-title {
        font-size: 1.75rem;
      }
      
      .rl-hero-sub {
        font-size: 0.9375rem;
      }
      
      .rl-hero .carousel-control-prev,
      .rl-hero .carousel-control-next {
        opacity: 0.7;
      }
    }
    
    /* Accessibility */
    @media (prefers-reduced-motion: reduce) {
      .rl-hero .carousel-item.active .carousel-caption {
        animation: none;
      }
      
      .rl-hero-badge {
        animation: none;
      }
      
      .rl-hero .carousel-indicators button:hover {
        transform: none;
      }
    }
  </style>
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
  </div>
  <div class="carousel-inner">
    <!-- Slide 1: Properties -->
    <div class="carousel-item active">
      <img src="https://images.pexels.com/photos/1396122/pexels-photo-1396122.jpeg" class="d-block w-100 hero-img" alt="Modern living room" loading="eager" decoding="async" fetchpriority="high">
      <div class="rl-hero-overlay"></div>
      <div class="carousel-caption">
        <span class="rl-hero-badge">🏡 Premium Properties</span>
        <h1 class="rl-hero-title">Find your next home</h1>
        <p class="rl-hero-sub">Browse handpicked properties tailored to your lifestyle.</p>
      </div>
    </div>
    
    <!-- Slide 2: Rooms -->
    <div class="carousel-item">
      <img src="https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg" class="d-block w-100 hero-img" alt="Cozy bedroom interior" loading="lazy" decoding="async">
      <div class="rl-hero-overlay"></div>
      <div class="carousel-caption">
        <span class="rl-hero-badge">🛏️ Comfortable Rooms</span>
        <h2 class="rl-hero-title">Rooms made for comfort</h2>
        <p class="rl-hero-sub">Discover budget-friendly rooms in great neighborhoods.</p>
      </div>
    </div>
    
    <!-- Slide 3: Location -->
    <div class="carousel-item">
      <img src="https://images.pexels.com/photos/2251247/pexels-photo-2251247.jpeg" class="d-block w-100 hero-img" alt="City apartment exterior" loading="lazy" decoding="async">
      <div class="rl-hero-overlay"></div>
      <div class="carousel-caption">
        <span class="rl-hero-badge">📍 Prime Locations</span>
        <h2 class="rl-hero-title">Live where it matters</h2>
        <p class="rl-hero-sub">Find listings close to work, schools, and transit.</p>
      </div>
    </div>
  </div>
</div>