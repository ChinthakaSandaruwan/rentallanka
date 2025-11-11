# RentalLanka Design System

## 🎨 Overview
A modern, responsive design system built on top of Bootstrap 5 with custom brand styling. All custom classes are prefixed with `rl-` to avoid conflicts with Bootstrap.

## 📦 What's Included
- **Brand Colors**: Primary (#004E98), Accent (#3A6EA5), Orange highlight (#FF6700)
- **Responsive Design**: Mobile-first, works on all screen sizes
- **Modern UI Components**: Cards, buttons, forms, pagination, headers
- **Smooth Interactions**: Hover effects, transitions, shadows
- **Web Font**: Inter font family for clean, modern typography

## 🚀 Quick Start

### Option 1: Link External CSS File (Recommended)
Add this to your `<head>` section after Bootstrap CSS:

```html
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<!-- Inter Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- RentalLanka Theme -->
<link href="<?php echo $base_url; ?>/public/assets/css/rentallanka-theme.css" rel="stylesheet">
```

### Option 2: Inline CSS
The CSS is already embedded in `all_rooms.php`. You can copy the `<style>` block to other pages.

## 🎯 Brand Colors

| Color Name | Hex Code | CSS Variable | Usage |
|------------|----------|--------------|-------|
| Primary | #004E98 | `--rl-primary` | Main brand color, buttons, links |
| Light Background | #EBEBEB | `--rl-bg-light` | Page background |
| Secondary | #C0C0C0 | `--rl-secondary` | Muted elements |
| Accent | #3A6EA5 | `--rl-accent` | Secondary actions, highlights |
| Dark/Orange | #FF6700 | `--rl-dark` | Call-to-action, badges |

## 📚 Component Guide

### Page Headers
```html
<header class="rl-page-header">
  <div class="container">
    <h1>
      <i class="bi bi-icon-name"></i>
      Page Title
      <span class="badge">123</span>
    </h1>
    <div class="rl-subtitle">Descriptive subtitle text</div>
  </div>
</header>
```

### Buttons
```html
<!-- Primary button -->
<button class="btn btn-rl-primary">Primary Action</button>

<!-- Outline button -->
<button class="btn btn-rl-outline">Secondary Action</button>

<!-- Accent button (orange) -->
<button class="btn btn-rl-accent">Call to Action</button>

<!-- View button -->
<a href="#" class="btn btn-view">View Details</a>
```

### Cards
```html
<article class="card rl-card">
  <div class="rl-card-media ratio ratio-16x9">
    <img src="image.jpg" alt="Title" loading="lazy">
    <span class="rl-status-badge available">Available</span>
  </div>
  <div class="rl-card-body">
    <h3 class="rl-card-title">Card Title</h3>
    <div class="rl-meta">Room • 2 Beds</div>
    <div class="rl-location">
      <i class="bi bi-geo-alt-fill"></i>
      <span>Colombo, Sri Lanka</span>
    </div>
    <div class="rl-price">LKR 45,000<span class="rl-text-soft">/day</span></div>
  </div>
  <div class="rl-card-footer">
    <!-- Actions here -->
  </div>
</article>
```

### Forms
```html
<div class="rl-filter-card">
  <label class="rl-filter-label">Location</label>
  <input type="text" class="form-control" placeholder="City or area">
</div>
```

### Pagination
```html
<nav aria-label="Pagination">
  <ul class="pagination rl-pagination justify-content-center">
    <li class="page-item"><a class="page-link" href="#">1</a></li>
    <li class="page-item active"><a class="page-link" href="#">2</a></li>
    <li class="page-item"><a class="page-link" href="#">3</a></li>
  </ul>
</nav>
```

### Empty States
```html
<div class="rl-empty">
  <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
  <h4 class="mt-3">No results found</h4>
  <p class="mb-0">Try adjusting your filters</p>
</div>
```

## 🎨 Utility Classes

| Class | Purpose |
|-------|---------|
| `.rl-text-soft` | Muted text color |
| `.rl-bg-card` | White card background |
| `.rl-shadow-sm` | Small shadow |
| `.rl-shadow-md` | Medium shadow |

## 📱 Responsive Breakpoints

The design system uses Bootstrap's standard breakpoints:
- **Mobile**: < 576px
- **Tablet**: 576px - 767px
- **Desktop**: 768px - 991px
- **Large Desktop**: 992px+

All components are tested and optimized for these screen sizes.

## 🔧 Customization

### Changing Brand Colors
Edit the CSS variables in `rentallanka-theme.css`:

```css
:root {
  --rl-primary: #004E98;      /* Change to your primary color */
  --rl-accent: #3A6EA5;       /* Change to your accent color */
  --rl-dark: #FF6700;         /* Change to your highlight color */
}
```

### Adjusting Border Radius
```css
:root {
  --rl-radius-sm: 8px;   /* Small radius (badges, labels) */
  --rl-radius-md: 12px;  /* Medium radius (buttons, inputs) */
  --rl-radius-lg: 16px;  /* Large radius (cards) */
}
```

### Modifying Shadows
```css
:root {
  --rl-shadow-sm: 0 2px 10px rgba(0,0,0,.06);
  --rl-shadow-md: 0 8px 24px rgba(0,0,0,.10);
  --rl-shadow-lg: 0 14px 40px rgba(0,0,0,.14);
}
```

## ✨ Best Practices

1. **Keep Bootstrap Classes**: Always keep existing Bootstrap classes (container, row, col-*, etc.)
2. **Add rl- Classes**: Layer custom rl- classes on top for styling
3. **Don't Modify Bootstrap**: Never edit Bootstrap core files
4. **Use Semantic HTML**: Use proper tags (article, header, nav, etc.)
5. **Accessibility**: Include alt text, aria labels, and proper contrast ratios
6. **Lazy Loading**: Add `loading="lazy"` to images below the fold
7. **Performance**: Use WebP images with fallbacks where possible

## 🎯 Further Improvements Suggestions

### 1. **Add Filter System**
Create a filter sidebar or top bar with:
- Location dropdown (provinces/districts/cities)
- Price range slider
- Room type checkboxes
- Amenities filters (WiFi, AC, Parking, etc.)

### 2. **Implement Sort Options**
Add sorting dropdown:
- Newest first
- Price: Low to High
- Price: High to Low
- Most popular

### 3. **Add Skeleton Loaders**
Show loading placeholders while fetching data:
```html
<div class="rl-card skeleton-loader">
  <div class="skeleton-media"></div>
  <div class="skeleton-body">
    <div class="skeleton-line"></div>
    <div class="skeleton-line short"></div>
  </div>
</div>
```

### 4. **Implement Dark Mode**
Add a theme toggle using CSS variables:
```css
[data-theme="dark"] {
  --rl-bg-light: #1a1a1a;
  --rl-card: #2d2d2d;
  --rl-text-700: #e0e0e0;
}
```

### 5. **Add Animations**
Use CSS animations for page transitions:
```css
.rl-card {
  animation: fadeInUp 0.4s ease;
}

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
```

### 6. **Implement Save Search**
Allow users to save their search criteria and get notifications

### 7. **Add Quick View Modal**
Let users preview room details in a modal without leaving the page

### 8. **Implement Map View**
Show listings on an interactive map (Google Maps or Mapbox)

### 9. **Add Comparison Feature**
Allow users to compare multiple rooms side by side

### 10. **Image Gallery**
Implement a lightbox/carousel for multiple room images

## 📝 Implementation Status

- ✅ `all_rooms.php` - Fully implemented
- ⏳ Other pages - Apply the same pattern

## 🤝 Contributing

To maintain consistency across the site:
1. Use the same class naming convention (`rl-*`)
2. Follow the color palette strictly
3. Test on mobile, tablet, and desktop
4. Ensure accessibility standards
5. Keep PHP backend logic unchanged

## 📞 Support

For questions or issues with the design system, refer to this documentation or check the implemented example in `all_rooms.php`.

---

**Built with ❤️ for RentalLanka**
