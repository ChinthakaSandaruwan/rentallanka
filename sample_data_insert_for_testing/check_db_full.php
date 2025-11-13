<?php
require_once __DIR__ . '/../config/config.php';

echo "===== DATABASE STATUS =====\n\n";

try {
    $res = db()->query("SELECT COUNT(*) as cnt FROM users WHERE role='owner'");
    $row = $res->fetch_assoc();
    echo "👤 Owner Users: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM properties");
    $row = $res->fetch_assoc();
    echo "🏠 Properties: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM properties WHERE status='available'");
    $row = $res->fetch_assoc();
    echo "   - Available: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM property_images");
    $row = $res->fetch_assoc();
    echo "📷 Property Images: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM property_locations");
    $row = $res->fetch_assoc();
    echo "📍 Property Locations: " . $row['cnt'] . "\n";
    
    echo "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM rooms");
    $row = $res->fetch_assoc();
    echo "🛏️  Rooms: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM rooms WHERE status='available'");
    $row = $res->fetch_assoc();
    echo "   - Available: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM room_images");
    $row = $res->fetch_assoc();
    echo "📷 Room Images: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM room_locations");
    $row = $res->fetch_assoc();
    echo "📍 Room Locations: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM room_meals");
    $row = $res->fetch_assoc();
    echo "🍽️  Room Meals: " . $row['cnt'] . "\n";
    
    echo "\n===========================\n";
    echo "✅ Sample data generation complete!\n";
    echo "\nAccess your website:\n";
    echo "- Home: http://localhost/rentallanka/\n";
    echo "- Properties: http://localhost/rentallanka/public/includes/search.php?type=property\n";
    echo "- Rooms: http://localhost/rentallanka/public/includes/search.php?type=room\n";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
