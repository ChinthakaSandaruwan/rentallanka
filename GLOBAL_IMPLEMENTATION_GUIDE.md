# RentalLanka Global Stylesheet Implementation Guide

## 🎨 Overview

A comprehensive, modern stylesheet that transforms your entire RentalLanka website with:
- **Your brand colors**: Primary #004E98, Accent #3A6EA5, Orange #FF6700
- **Full responsiveness**: Desktop, tablet, mobile
- **Modern UI/UX**: Gradients, shadows, smooth transitions
- **Bootstrap enhancement**: Keeps Bootstrap classes, adds custom styling
- **No PHP changes**: Pure front-end improvements

---

## 📦 What's Included

### File Created
- `public/assets/css/rentallanka-global.css` (1014 lines)

### Key Features
✅ CSS variables for easy customization  
✅ Enhanced Bootstrap components (buttons, cards, forms, alerts, modals, etc.)  
✅ Custom components (section titles, badges, price displays, status indicators)  
✅ Smooth animations and transitions  
✅ Responsive breakpoints (1199px, 991px, 767px, 575px)  
✅ Accessibility support (focus states, reduced motion, high contrast)  
✅ Print-friendly styles  

---

## 🚀 Quick Start (3 Steps)

### Step 1: Link the Stylesheet
Add this line in the `<head>` section of **EVERY page** (or in a global header file):

```html
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- RentalLanka Global Styles -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/rentallanka-global.css">
```

**Note**: Place this AFTER your Bootstrap CSS link so it can override Bootstrap defaults.

### Step 2: Verify Structure
Your typical HTML structure should look like:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Bootstrap CSS -->
    <link href="path/to/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- RentalLanka Global Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/rentallanka-global.css">
    
    <!-- Any other custom CSS -->
</head>
<body>
    <!-- Your content -->
</body>
</html>
```

### Step 3: Test
Open any page in your browser and you should see:
- Modern button gradients (blue gradient on primary buttons)
- Smooth hover effects on cards (lift up on hover)
- Better spacing and typography
- Rounded corners on buttons and cards
- Brand colors applied throughout

---

## 🎯 What Gets Automatically Styled

### Bootstrap Components Enhanced
All standard Bootstrap elements are automatically enhanced:

| Component | Enhancement |
|-----------|-------------|
| **Buttons** | Gradient backgrounds, lift on hover, better shadows |
| **Cards** | Rounded corners, smooth hover lift, image zoom effect |
| **Forms** | Better focus states, brand color borders |
| **Alerts** | Gradient backgrounds, left accent border |
| **Modals** | Larger border radius, better shadows |
| **Badges** | Pill-shaped, brand colors |
| **Dropdowns** | Rounded menu, smooth hover states |
| **Pagination** | Modern styling, lift on hover |
| **Tables** | Gradient header, hover row highlight |
| **Tabs** | Orange underline on active tab |

### No Code Changes Required
Your existing HTML with Bootstrap classes will automatically get the new styling:

```html
<!-- This button will automatically get gradient styling -->
<button class="btn btn-primary">Click Me</button>

<!-- This card will automatically lift on hover -->
<div class="card">
  <div class="card-body">
    <h5 class="card-title">Property Title</h5>
    <p class="card-text">Description here</p>
  </div>
</div>
```

---

## 🎨 Custom Classes Available

Use these classes for additional styling:

### Section Titles
```html
<h2 class="rl-section-title">Featured Properties</h2>
<!-- Includes orange underline accent -->
```

### Price Display
```html
<span class="rl-price">Rs. 25,000</span>
<!-- Large orange text -->

<span class="rl-price-small">Rs. 15,000</span>
<!-- Smaller version -->
```

### Status Indicators
```html
<span class="rl-status rl-status-available">Available</span>
<span class="rl-status rl-status-pending">Pending</span>
<span class="rl-status rl-status-booked">Booked</span>
```

### Featured Badge
```html
<span class="rl-badge-featured">Featured</span>
<!-- Orange gradient badge with shadow -->
```

### Info Box
```html
<div class="rl-info-box">
  <p>Important information for users</p>
</div>
<!-- Blue gradient box with left accent border -->
```

### Icon Box
```html
<div class="rl-icon-box">
  <i class="bi bi-house"></i>
</div>
<!-- Rounded box with gradient background for icons -->
```

### Feature Grid
```html
<div class="rl-feature-grid">
  <div class="rl-feature-item">
    <h4>Feature 1</h4>
    <p>Description</p>
  </div>
  <div class="rl-feature-item">
    <h4>Feature 2</h4>
    <p>Description</p>
  </div>
</div>
<!-- Auto-responsive grid layout -->
```

### Animations
```html
<div class="card animate-fade-in-up">...</div>
<div class="card animate-fade-in">...</div>
<div class="card animate-slide-in">...</div>
```

### Hover Effects
```html
<div class="card hover-lift">...</div>
<div class="card hover-glow">...</div>
```

---

## 🎨 Brand Colors Reference

Use these utility classes anywhere:

```html
<!-- Text Colors -->
<p class="text-primary">Blue text (#004E98)</p>
<p class="text-accent">Light blue text (#3A6EA5)</p>
<p class="text-orange">Orange text (#FF6700)</p>
<p class="text-muted">Gray text</p>

<!-- Background Colors -->
<div class="bg-light-custom">Light gray background (#EBEBEB)</div>
<div class="bg-primary-gradient">Blue gradient background</div>

<!-- Shadows -->
<div class="shadow-xs">Extra small shadow</div>
<div class="shadow-sm">Small shadow</div>
<div class="shadow-md">Medium shadow</div>
<div class="shadow-lg">Large shadow</div>
<div class="shadow-xl">Extra large shadow</div>

<!-- Border Radius -->
<div class="rounded-sm">Small radius (6px)</div>
<div class="rounded-md">Medium radius (10px)</div>
<div class="rounded-lg">Large radius (16px)</div>
<div class="rounded-xl">Extra large radius (24px)</div>
<div class="rounded-full">Full rounded (pill shape)</div>
```

---

## 📱 Responsive Behavior

The stylesheet automatically adjusts for different screen sizes:

| Screen Size | Changes |
|-------------|---------|
| **Desktop (>1199px)** | Full sizing, optimal spacing |
| **Laptop (992px-1199px)** | Slightly smaller fonts |
| **Tablet (768px-991px)** | Reduced padding, stacked layouts |
| **Mobile (576px-767px)** | Smaller fonts, full-width buttons |
| **Small Mobile (<576px)** | Minimal padding, compact tables |

---

## 🔧 Advanced Customization

### Changing Brand Colors
Edit these CSS variables in `rentallanka-global.css` (lines 11-17):

```css
:root {
  --rl-primary: #004E98;      /* Main blue */
  --rl-light-bg: #EBEBEB;     /* Light gray */
  --rl-secondary: #C0C0C0;    /* Medium gray */
  --rl-accent: #3A6EA5;       /* Light blue */
  --rl-dark: #FF6700;         /* Orange */
}
```

### Changing Shadows
Lines 30-35:
```css
:root {
  --rl-shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
  --rl-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
  /* etc. */
}
```

### Changing Border Radius
Lines 37-42:
```css
:root {
  --rl-radius-sm: 6px;
  --rl-radius-md: 10px;
  /* etc. */
}
```

---

## ✅ Implementation Checklist

- [ ] Copy `rentallanka-global.css` to `public/assets/css/`
- [ ] Add Google Fonts link to all pages
- [ ] Add global CSS link to all pages (after Bootstrap)
- [ ] Test on desktop browser
- [ ] Test on tablet (resize browser or use dev tools)
- [ ] Test on mobile (resize browser or use dev tools)
- [ ] Check buttons, cards, forms look good
- [ ] Check hover effects work smoothly
- [ ] Verify brand colors appear correctly

---

## 🐛 Troubleshooting

### Styles Not Applying?
1. Check that the CSS file path is correct
2. Make sure the CSS link is AFTER Bootstrap CSS
3. Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
4. Check browser console for 404 errors

### Bootstrap Styles Still Showing?
1. Ensure the global CSS link is placed after Bootstrap
2. Some Bootstrap styles may need `!important` to override (already included where needed)

### Fonts Look Different?
1. Verify the Google Fonts link is present
2. Check browser console for font loading errors
3. Font fallbacks will work even if Google Fonts fail to load

### Mobile Layout Issues?
1. Ensure viewport meta tag is present:
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```
2. Test in actual device or browser dev tools responsive mode

---

## 📊 File Size & Performance

- **CSS file size**: ~35KB unminified
- **Gzipped**: ~7KB
- **Load time**: <100ms on typical connection
- **No external dependencies** except Google Fonts (optional)

---

## 🎓 Best Practices

### DO ✅
- Link the stylesheet on every page
- Use Bootstrap classes as normal
- Add custom `rl-` classes for special components
- Test responsive behavior
- Keep the CSS file in `assets/css/` folder

### DON'T ❌
- Modify Bootstrap core files
- Inline styles that override global CSS
- Remove Bootstrap classes
- Change PHP backend code
- Place CSS link before Bootstrap

---

## 🚀 Next Steps

1. **Apply to all pages**: Make sure every PHP file in your public folder links to this stylesheet
2. **Test thoroughly**: Check all pages on different devices
3. **Customize**: Adjust colors, shadows, or spacing to your preference
4. **Optimize images**: Consider adding lazy loading for property images
5. **Add transitions**: Use animation classes on page load for better UX

---

## 💡 Examples for Common Pages

### Homepage
```html
<section class="py-5">
  <div class="container">
    <h2 class="rl-section-title">Featured Properties</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card hover-lift">
          <img src="property.jpg" class="card-img-top" alt="Property">
          <div class="card-body">
            <h5 class="card-title">Modern Apartment</h5>
            <p class="card-text">Beautiful 2BR apartment in Colombo</p>
            <p class="rl-price-small">Rs. 45,000/month</p>
            <a href="#" class="btn btn-primary w-100">View Details</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
```

### Property Details Page
```html
<div class="container my-5">
  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <img src="property.jpg" class="card-img-top" alt="Property">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <h1 class="card-title mb-0">Luxury Villa in Kandy</h1>
            <span class="rl-status rl-status-available">Available</span>
          </div>
          <p class="rl-price mb-3">Rs. 150,000 / month</p>
          <p class="card-text">Description of the property...</p>
          <button class="btn btn-primary btn-lg">Contact Owner</button>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Property Details</h5>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Bedrooms: 3</li>
            <li class="list-group-item">Bathrooms: 2</li>
            <li class="list-group-item">Area: 1500 sq ft</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Contact Form
```html
<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h2 class="rl-section-title">Contact Us</h2>
          <form>
            <div class="mb-3">
              <label for="name" class="form-label">Your Name</label>
              <input type="text" class="form-control" id="name" placeholder="John Doe">
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email Address</label>
              <input type="email" class="form-control" id="email" placeholder="john@example.com">
            </div>
            <div class="mb-3">
              <label for="message" class="form-label">Message</label>
              <textarea class="form-control" id="message" rows="4" placeholder="Your message..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">Send Message</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
```

---

## 🎉 Summary

You now have a professional, modern stylesheet that:
- ✅ Works with your existing Bootstrap code
- ✅ Uses your brand colors throughout
- ✅ Is fully responsive
- ✅ Requires minimal implementation effort
- ✅ Enhances UI/UX automatically
- ✅ Doesn't touch any PHP code

**Just link the CSS file and watch your entire site transform!**

---

## 📞 Support

If you need specific pages styled differently or have questions:
1. Check this guide first
2. Review the CSS file comments
3. Test in browser dev tools (F12)
4. Verify Bootstrap classes are correct

**Happy coding! 🚀**
