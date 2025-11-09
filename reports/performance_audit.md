# Rentallanka Performance Audit

Date: YYYY-MM-DD
Environment: Windows/XAMPP (Apache, PHP, MySQL), local base URL: http://localhost/rentallanka/

## Scope
- Homepage
- Property list: /public/includes/all_properties.php
- Search results: /public/includes/advance_search.php?type=property&q=house

## Baseline Lighthouse (before changes)
- Desktop
  - Home: Performance __, Accessibility __, Best Practices __, SEO __
  - Properties: __ / __ / __ / __
  - Search: __ / __ / __ / __
- Mobile
  - Home: __ / __ / __ / __
  - Properties: __ / __ / __ / __
  - Search: __ / __ / __ / __

## Server changes applied
- Static assets
  - Gzip/Brotli enabled in .htaccess (mod_deflate / mod_brotli)
  - Cache-Control/Expires for images, fonts, CSS, JS
  - Defer for non-critical JS on list/search pages
- PHP
  - OpCache recommended settings (php.ini):
```
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.jit_buffer_size=0
```
- MySQL indexes & query rewrites
  - FULLTEXT on properties(title,description), rooms(title,description)
  - Composite: properties(status,price_per_month), locations(property_id,province_id,district_id)
  - Keyset pagination (lists/search), MATCH() for keyword search
- Images
  - Upload-time resize to 1600x1200, thumbnails 480x360
  - Listings lazy-load, WebP-first where available

## Slow query log
- Enabled (session):
```
SET GLOBAL log_output = 'FILE';
SET GLOBAL slow_query_log_file = 'C:/xampp/htdocs/rentallanka/error/slow.log';
SET GLOBAL long_query_time = 0.5;
SET GLOBAL slow_query_log = ON;
```
- Collected for: 30 minutes while browsing Home/Properties/Search
- Disabled:
```
SET GLOBAL slow_query_log = OFF;
```

### Top 5 slow queries
Paste mysqldumpslow output or manual selection here.
For each query, include:
- Query
- EXPLAIN plan
- Proposed index/rewrites

Template:
```
Query: SELECT ...
EXPLAIN: key=..., type=..., rows=..., Extra=...
Fix: add/use index ..., or rewrite to keyset pagination / MATCH ...
```

## OpCache timing
- Method: measure 3 reloads per page before/after enabling OpCache; use Chrome DevTools (Finish time) or server timing.
- Results (avg of 3):
  - Home: before __ ms → after __ ms
  - Properties: before __ ms → after __ ms
  - Search: before __ ms → after __ ms

## Lighthouse (after changes)
- Desktop: Home __ / __ / __ / __; Properties __ / __ / __ / __; Search __ / __ / __ / __
- Mobile: Home __ / __ / __ / __; Properties __ / __ / __ / __; Search __ / __ / __ / __

## Issues & follow-ups
- Warnings in advance_search.php (Array to string conversion at lines ...). Action: fix variable types/concats.
- Any remaining large images not thumbnailed or missing WebP copy.
- Any list/search queries still doing full scans.

## Appendix
### Lighthouse commands (PowerShell)
```
New-Item -ItemType Directory -Force -Path reports/lighthouse | Out-Null
npx lighthouse http://localhost/rentallanka/ --preset=desktop --output html --output-path reports/lighthouse/home-desktop.html --quiet
npx lighthouse http://localhost/rentallanka/public/includes/all_properties.php --preset=desktop --output html --output-path reports/lighthouse/all-properties-desktop.html --quiet
npx lighthouse "http://localhost/rentallanka/public/includes/advance_search.php?type=property&q=house" --preset=desktop --output html --output-path reports/lighthouse/search-desktop.html --quiet

npx lighthouse http://localhost/rentallanka/ --preset=mobile --output html --output-path reports/lighthouse/home-mobile.html --quiet
npx lighthouse http://localhost/rentallanka/public/includes/all_properties.php --preset=mobile --output html --output-path reports/lighthouse/all-properties-mobile.html --quiet
npx lighthouse "http://localhost/rentallanka/public/includes/advance_search.php?type=property&q=house" --preset=mobile --output html --output-path reports/lighthouse/search-mobile.html --quiet
```

### EXPLAIN examples
```
EXPLAIN SELECT p.property_id, p.title FROM properties p WHERE p.status='available' AND p.property_id < 12345 ORDER BY p.property_id DESC LIMIT 20;
EXPLAIN SELECT property_id, MATCH(title,description) AGAINST('+house +garden*' IN BOOLEAN MODE) AS rel FROM properties WHERE MATCH(title,description) AGAINST('+house +garden*' IN BOOLEAN MODE) ORDER BY rel DESC, property_id DESC LIMIT 20;
```
