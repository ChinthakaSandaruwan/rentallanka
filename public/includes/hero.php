<!-- Beautiful Hero Section -->
<section class="hero position-relative text-center text-white">
  <div class="hero-bg position-absolute top-0 start-0 w-100 h-100"
       style="background-image: url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg');
              background-size: cover;
              background-position: center;
              filter: brightness(0.7);">
  </div>

  <div class="hero-overlay position-absolute top-0 start-0 w-100 h-100"
       style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7));">
  </div>

  <div class="hero-content position-relative d-flex flex-column justify-content-center align-items-center h-100">
    <h1 class="display-4 fw-bold mb-3 animate-fade-in">Welcome to Our World</h1>
    <p class="lead mb-4 animate-fade-in delay-1s">Discover, Learn, and Grow with Us</p>
    <a href="#!" class="btn btn-light btn-lg rounded-pill px-4 shadow-lg animate-fade-in delay-2s">
      Get Started
    </a>
  </div>
</section>

<!-- Add some spacing below -->
<div class="my-5"></div>

<style>
  .hero {
    height: 100vh; /* Full height */
    overflow: hidden;
  }

  @media (max-height: 700px) {
    .hero {
      height: 80vh;
    }
  }

  /* Smooth fade-in animation */
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

  .animate-fade-in {
    animation: fadeInUp 1s ease forwards;
    opacity: 0;
  }

  .delay-1s {
    animation-delay: 0.5s;
  }

  .delay-2s {
    animation-delay: 1s;
  }

  /* Optional: subtle parallax effect */
  .hero-bg {
    transform: scale(1.1);
    transition: transform 5s ease;
  }

  .hero:hover .hero-bg {
    transform: scale(1.15);
  }
</style>
