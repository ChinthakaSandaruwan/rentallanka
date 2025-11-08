document.addEventListener('DOMContentLoaded', () => {
  const links = Array.from(document.querySelectorAll('[data-delete-link]'));
  links.forEach(a => {
    a.addEventListener('click', (e) => {
      // Prevent accidental double clicks
      if (a.dataset.locked === '1') {
        e.preventDefault();
        return;
      }
      const ok = window.confirm('Delete this property and all its images?');
      if (!ok) {
        e.preventDefault();
        return;
      }
      a.dataset.locked = '1';
    });
  });
});

