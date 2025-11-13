/**
 * RentalLanka Performance Optimization Script
 * Handles lazy loading, image optimization, and performance enhancements
 */

(function() {
  'use strict';

  // ============================================
  // LAZY LOADING POLYFILL FOR OLDER BROWSERS
  // ============================================
  if ('loading' in HTMLImageElement.prototype === false) {
    // Intersection Observer-based lazy loading
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
              img.src = img.dataset.src;
              img.removeAttribute('data-src');
            }
            if (img.dataset.srcset) {
              img.srcset = img.dataset.srcset;
              img.removeAttribute('data-srcset');
            }
            observer.unobserve(img);
          }
        });
      }, {
        rootMargin: '50px 0px',
        threshold: 0.01
      });

      document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
        observer.observe(img);
      });
    } else {
      // Fallback: load all images immediately
      document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
        if (img.dataset.src) img.src = img.dataset.src;
        if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      });
    }
  }

  // ============================================
  // WEBP SUPPORT DETECTION
  // ============================================
  function supportsWebP() {
    const elem = document.createElement('canvas');
    if (elem.getContext && elem.getContext('2d')) {
      return elem.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }
    return false;
  }

  // Add class to html element for CSS targeting
  if (supportsWebP()) {
    document.documentElement.classList.add('webp');
  } else {
    document.documentElement.classList.add('no-webp');
  }

  // ============================================
  // PRELOAD CRITICAL IMAGES ON IDLE
  // ============================================
  function preloadCriticalImages() {
    const criticalImages = document.querySelectorAll('[data-preload="critical"]');
    criticalImages.forEach((img) => {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.as = 'image';
      link.href = img.src || img.dataset.src;
      if (img.srcset || img.dataset.srcset) {
        link.imageSrcset = img.srcset || img.dataset.srcset;
      }
      document.head.appendChild(link);
    });
  }

  if ('requestIdleCallback' in window) {
    requestIdleCallback(preloadCriticalImages);
  } else {
    setTimeout(preloadCriticalImages, 2000);
  }

  // ============================================
  // REDUCE IMAGE QUALITY ON SLOW CONNECTIONS
  // ============================================
  if ('connection' in navigator) {
    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (conn && (conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g')) {
      document.documentElement.classList.add('slow-connection');
    }
  }

  // ============================================
  // DEFER OFFSCREEN IMAGES
  // ============================================
  function deferOffscreenImages() {
    const images = document.querySelectorAll('img[data-defer]');
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.defer;
            img.removeAttribute('data-defer');
            imageObserver.unobserve(img);
          }
        });
      }, {
        rootMargin: '100px'
      });

      images.forEach((img) => imageObserver.observe(img));
    } else {
      images.forEach((img) => {
        img.src = img.dataset.defer;
        img.removeAttribute('data-defer');
      });
    }
  }

  // Run after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', deferOffscreenImages);
  } else {
    deferOffscreenImages();
  }

  // ============================================
  // RESOURCE HINTS FOR EXTERNAL DOMAINS
  // ============================================
  function addResourceHints() {
    const hints = [
      { rel: 'dns-prefetch', href: 'https://cdn.jsdelivr.net' },
      { rel: 'dns-prefetch', href: 'https://images.pexels.com' },
      { rel: 'preconnect', href: 'https://cdn.jsdelivr.net', crossorigin: true },
    ];

    hints.forEach(({ rel, href, crossorigin }) => {
      const existing = document.querySelector(`link[rel="${rel}"][href="${href}"]`);
      if (!existing) {
        const link = document.createElement('link');
        link.rel = rel;
        link.href = href;
        if (crossorigin) link.crossOrigin = 'anonymous';
        document.head.appendChild(link);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addResourceHints);
  } else {
    addResourceHints();
  }

  // ============================================
  // PREVENT LAYOUT SHIFT FOR IMAGES
  // ============================================
  function preventLayoutShift() {
    document.querySelectorAll('img:not([width]):not([height])').forEach((img) => {
      if (img.naturalWidth && img.naturalHeight) {
        const aspectRatio = (img.naturalHeight / img.naturalWidth) * 100;
        img.style.aspectRatio = `${img.naturalWidth} / ${img.naturalHeight}`;
      }
    });
  }

  window.addEventListener('load', preventLayoutShift);

  // ============================================
  // REDUCE MOTION FOR ACCESSIBILITY
  // ============================================
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('reduce-motion');
  }

  // ============================================
  // PAGE VISIBILITY API - PAUSE HEAVY OPERATIONS
  // ============================================
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      // Pause carousels, videos, animations when tab is hidden
      document.querySelectorAll('.carousel').forEach((carousel) => {
        const instance = bootstrap.Carousel.getInstance(carousel);
        if (instance) instance.pause();
      });
    }
  });

  // ============================================
  // LOG PERFORMANCE METRICS (DEV MODE ONLY)
  // ============================================
  if (window.location.search.includes('perf=1')) {
    window.addEventListener('load', () => {
      if ('performance' in window) {
        const perfData = window.performance.timing;
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
        const connectTime = perfData.responseEnd - perfData.requestStart;
        const renderTime = perfData.domComplete - perfData.domLoading;

        console.group('🚀 Performance Metrics');
        console.log('Page Load Time:', pageLoadTime + 'ms');
        console.log('Connection Time:', connectTime + 'ms');
        console.log('Render Time:', renderTime + 'ms');
        console.log('DOM Content Loaded:', (perfData.domContentLoadedEventEnd - perfData.navigationStart) + 'ms');
        console.groupEnd();

        // Log to server if endpoint available
        if (typeof fetch !== 'undefined') {
          fetch('/api/metrics', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              pageLoadTime,
              connectTime,
              renderTime,
              url: window.location.href
            })
          }).catch(() => {}); // Fail silently
        }
      }
    });
  }

  // ============================================
  // EXPORT FOR EXTERNAL USE
  // ============================================
  window.RLPerformance = {
    supportsWebP,
    preloadCriticalImages,
    deferOffscreenImages
  };

})();
