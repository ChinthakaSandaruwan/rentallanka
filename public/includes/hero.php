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
?>
<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
  <!-- dont add buttons and a tags to navigate other pages -->
  <style>
    @media (max-width: 576px){
      #heroCarousel .hero-img{max-height:45vh;}
      #heroCarousel .hero-title{font-size:1.75rem;}
      #heroCarousel .hero-sub{font-size:1rem;}
      #heroCarousel .hero-cta .btn{padding:.5rem 1rem;font-size:.9rem}
    }
  </style>
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
  </div>
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="https://images.pexels.com/photos/1396122/pexels-photo-1396122.jpeg" class="d-block w-100 hero-img" alt="Modern living room" style="max-height:70vh;object-fit:cover;" loading="eager" decoding="async" fetchpriority="high">
      <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(180deg,rgba(0,0,0,.55),rgba(0,0,0,.35));"></div>
      <div class="carousel-caption d-flex flex-column align-items-center justify-content-center h-100 px-3 text-center text-md-start">
        <h1 class="fw-bold mb-2 hero-title">Find your next home</h1>
        <p class="mb-4 hero-sub">Browse handpicked properties tailored to your lifestyle.</p>
        <div class="d-flex gap-2 flex-wrap hero-cta justify-content-center justify-content-md-start">
        </div>
      </div>
    </div>
    <div class="carousel-item">
      <img src="https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg" class="d-block w-100 hero-img" alt="Cozy bedroom interior" style="max-height:70vh;object-fit:cover;" loading="lazy" decoding="async">
      <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(180deg,rgba(0,0,0,.55),rgba(0,0,0,.35));"></div>
      <div class="carousel-caption d-flex flex-column align-items-center justify-content-center h-100 px-3 text-center text-md-start">
        <h2 class="fw-bold mb-2 hero-title">Rooms made for comfort</h2>
        <p class="mb-4 hero-sub">Discover budget-friendly rooms in great neighborhoods.</p>
        <div class="d-flex gap-2 flex-wrap hero-cta justify-content-center justify-content-md-start">
        </div>
      </div>
    </div>
    <div class="carousel-item">
      <img src="https://images.pexels.com/photos/2251247/pexels-photo-2251247.jpeg" class="d-block w-100 hero-img" alt="City apartment exterior" style="max-height:70vh;object-fit:cover;" loading="lazy" decoding="async">
      <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(180deg,rgba(0,0,0,.55),rgba(0,0,0,.35));"></div>
      <div class="carousel-caption d-flex flex-column align-items-center justify-content-center h-100 px-3 text-center text-md-start">
        <h2 class="fw-bold mb-2 hero-title">Live where it matters</h2>
        <p class="mb-4 hero-sub">Find listings close to work, schools, and transit.</p>
        <div class="d-flex gap-2 flex-wrap hero-cta justify-content-center justify-content-md-start">
        </div>
      </div>
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>