# Sample Data Generation - Complete

## ✅ Summary

Successfully generated comprehensive sample data for the RentalLanka website testing environment.

## 📊 Database Contents

### Users
- **Owner Users**: 1
- Owner ID: 2

### Properties
- **Total Properties**: 45
- **Status**: All Available (45)
- **Images**: 101 property images (2-4 per property)
- **Locations**: 45 property locations with randomized provinces, districts, cities
- **Features**: Each property has randomized:
  - Property type (apartment, house, villa, duplex, studio, etc.)
  - Bedrooms (0-5)
  - Bathrooms (1-4)
  - Living rooms (0-2)
  - Square footage (400-3500)
  - Monthly price (LKR 15,000 - 250,000)
  - Amenities (garden, gym, pool, kitchen, parking, water, electricity)

### Rooms
- **Total Rooms**: 29
- **Status**: All Available (29)
- **Images**: 83 room images (1-4 per room)
- **Locations**: 29 room locations with randomized provinces, districts, cities
- **Meal Plans**: 21 room meal options (breakfast, half board, full board, all inclusive)
- **Features**: Each room has randomized:
  - Room type (single, double, twin, suite, deluxe, family, studio, etc.)
  - Beds (1-3)
  - Maximum guests (1-4)
  - Daily price (LKR 1,500 - 15,000)
  - Optional meal plans with prices

## 📁 File System

### Uploaded Images
- **Properties**: `/uploads/properties/` - 101 files
- **Rooms**: `/uploads/rooms/` - 83 files

### Sample Source Images
- **Property Images**: `/sample_data_insert_for_testing/images/property_images/` - 15+ stock photos
- **Room Images**: `/sample_data_insert_for_testing/images/room_images/` - 15+ stock photos

## 🛠️ Generated Scripts

### Main Scripts (Original)
1. **index.php** - Web UI for manual generation
2. **insert_property.php** - Property generation endpoint
3. **insert_rooms.php** - Room generation endpoint  
4. **delete_all.php** - Cleanup script (with CSRF protection)

### New Helper Scripts
1. **check_db.php** - Quick database counts check
2. **check_db_full.php** - Detailed database status with emoji output
3. **gen_rooms_only.php** - Standalone room generator (25 rooms)
4. **generate_all.php** - Automated batch generation (15 properties + 25 rooms)
5. **test_rooms.php** - Room generation test script

## 🌐 Access Points

### Public Pages
- **Home**: http://localhost/rentallanka/
- **Properties Search**: http://localhost/rentallanka/public/includes/search.php?type=property
- **Rooms Search**: http://localhost/rentallanka/public/includes/search.php?type=room
- **Sample Property**: http://localhost/rentallanka/public/includes/view_property.php?id=1
- **Sample Room**: http://localhost/rentallanka/public/includes/view_room.php?id=1

### Admin/Generator
- **Sample Data Generator**: http://localhost/rentallanka/sample_data_insert_for_testing/

## 🔄 How to Regenerate Data

### Option 1: Web UI (Manual)
```
Navigate to: http://localhost/rentallanka/sample_data_insert_for_testing/
- Use forms to generate properties/rooms
- Select owner, count, and initial status
- Click generate button
```

### Option 2: CLI (Automated)
```bash
# Generate 25 rooms
php C:\xampp\htdocs\rentallanka\sample_data_insert_for_testing\gen_rooms_only.php

# Check database status
php C:\xampp\htdocs\rentallanka\sample_data_insert_for_testing\check_db_full.php
```

### Option 3: Delete All & Regenerate
```bash
# WARNING: This deletes ALL sample data!
# Access via web UI with CSRF protection:
# http://localhost/rentallanka/sample_data_insert_for_testing/
# Click "Delete All Sample Data" button

# Then regenerate
php C:\xampp\htdocs\rentallanka\sample_data_insert_for_testing\gen_rooms_only.php
```

## 📋 Data Structure

### Property Fields
- property_id, property_code (PROP-XXXXXX)
- owner_id, title, description
- price_per_month, bedrooms, bathrooms, living_rooms
- garden, gym, pool, kitchen, parking
- water_supply, electricity_supply
- sqft, property_type, status, image
- created_at, updated_at

### Room Fields
- room_id, room_code (ROOM-XXXXXX)
- owner_id, title, description
- room_type, beds, maximum_guests
- price_per_day, status
- created_at, updated_at

### Location Fields (Both)
- province_id, district_id, city_id
- address (randomized: "No. XX, Test Street")
- postal_code (5-digit random)
- google_map_link (null)

### Images (Both)
- Linked to parent property/room
- One image marked as primary (is_primary=1)
- Full URL path stored in database
- Physical files stored in /uploads/

## ✨ Features

1. **Randomized Content**
   - Titles: Adjective + Type combinations
   - Descriptions: 3 random phrases combined
   - All numeric values randomized within realistic ranges

2. **Complete Geographic Data**
   - Random province selection from database
   - Random district within province
   - Random city within district
   - Ensures referential integrity

3. **Images**
   - 1-4 images per property/room
   - Copied from sample images folder
   - Unique filenames with timestamp + random suffix
   - Primary image set for thumbnails

4. **Status Management**
   - All generated items set to "available" by default
   - Can specify "pending" or "unavailable" via web UI

5. **Meal Plans (Rooms Only)**
   - 50% of rooms get meal plans
   - Random 1-4 meal types per room
   - Breakfast, Half Board, Full Board, All Inclusive
   - Prices: LKR 800-3000

## 🎯 Testing Capabilities

With this sample data, you can now test:
- Property listings and search
- Room listings and search
- Property detail pages
- Room detail pages
- Image galleries
- Location-based filtering
- Price range filtering
- Amenity filtering
- Booking flows
- Owner dashboards
- Approval workflows
- Wishlist functionality
- Review systems

## 📝 Notes

- All sample data uses Owner ID: 2
- Property codes format: PROP-000001, PROP-000002, etc.
- Room codes format: ROOM-000001, ROOM-000002, etc.
- Sample images are from Pexels (stock photos)
- Database supports multiple owners - can generate data for different owners via web UI
- Safe to delete and regenerate - no production data affected

## 🚀 Next Steps

1. ✅ Sample data generated successfully
2. 🔍 Test property search and filtering
3. 🔍 Test room search and filtering  
4. 📱 Test responsive design on mobile
5. 🎨 Verify all images load correctly
6. 🧪 Test booking workflows
7. 👤 Test owner dashboard functionality
8. 📊 Generate additional data if needed (use web UI)

---

**Generated**: Multiple batches
**Total Generation Time**: ~10-15 seconds
**Database Engine**: MySQL via XAMPP
**PHP Version**: 7.4+
