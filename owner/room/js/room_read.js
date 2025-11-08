 (() => {
   // No actions on the list; just minor UX helpers.
   document.addEventListener('DOMContentLoaded', () => {
     // Enable Bootstrap dismiss on any alert already in DOM.
     // And basic lazy loading fallback for images if loading attr unsupported.
     const imgs = document.querySelectorAll('img.card-img-top');
     imgs.forEach(img => {
       if (!('loading' in HTMLImageElement.prototype)) return;
       img.setAttribute('loading', 'lazy');
     });
   });
 })();

