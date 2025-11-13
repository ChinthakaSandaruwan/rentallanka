<?php
require_once __DIR__ . '/../config/config.php';

echo "===== SAMPLE DATA GENERATION =====\n\n";

// Check if owners exist
$res = db()->query("SELECT COUNT(*) as cnt FROM users WHERE role='owner'");
$row = $res->fetch_assoc();
$owner_count = (int)$row['cnt'];

if ($owner_count === 0) {
    echo "ERROR: No owner users found in database. Please create at least one owner user first.\n";
    exit(1);
}

echo "Found {$owner_count} owner(s) in database.\n\n";

// Get the first owner ID
$res = db()->query("SELECT user_id FROM users WHERE role='owner' ORDER BY user_id ASC LIMIT 1");
$owner = $res->fetch_assoc();
$owner_id = (int)$owner['user_id'];

echo "Using owner ID: {$owner_id}\n\n";

// Generate Properties
echo "--- Generating 15 Properties ---\n";
$_POST = [
    'count' => 15,
    'owner_id' => $owner_id,
    'status' => 'available'
];
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
try {
    include __DIR__ . '/insert_property.php';
    $output = ob_get_clean();
    // Extract result
    preg_match('/Created: (\d+)/', $output, $matches);
    $props_created = $matches[1] ?? 0;
    preg_match('/Errors: (\d+)/', $output, $matches);
    $props_errors = $matches[1] ?? 0;
    echo "Properties Created: {$props_created}\n";
    echo "Properties Errors: {$props_errors}\n\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR generating properties: " . $e->getMessage() . "\n\n";
    $props_created = 0;
    $props_errors = 15;
}

// Generate Rooms
echo "--- Generating 25 Rooms ---\n";
$_POST = [
    'count' => 25,
    'owner_id' => $owner_id,
    'status' => 'available'
];
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
try {
    include __DIR__ . '/insert_rooms.php';
    $output = ob_get_clean();
    // Extract result
    preg_match('/Created: (\d+)/', $output, $matches);
    $rooms_created = $matches[1] ?? 0;
    preg_match('/Errors: (\d+)/', $output, $matches);
    $rooms_errors = $matches[1] ?? 0;
    echo "Rooms Created: {$rooms_created}\n";
    echo "Rooms Errors: {$rooms_errors}\n\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR generating rooms: " . $e->getMessage() . "\n\n";
    $rooms_created = 0;
    $rooms_errors = 25;
}

// Final summary
echo "===== GENERATION COMPLETE =====\n\n";

$res = db()->query("SELECT COUNT(*) as cnt FROM properties");
$row = $res->fetch_assoc();
echo "Total Properties: " . $row['cnt'] . "\n";

$res = db()->query("SELECT COUNT(*) as cnt FROM rooms");
$row = $res->fetch_assoc();
echo "Total Rooms: " . $row['cnt'] . "\n";

$res = db()->query("SELECT COUNT(*) as cnt FROM property_images");
$row = $res->fetch_assoc();
echo "Total Property Images: " . $row['cnt'] . "\n";

$res = db()->query("SELECT COUNT(*) as cnt FROM room_images");
$row = $res->fetch_assoc();
echo "Total Room Images: " . $row['cnt'] . "\n";

echo "\nYou can now browse properties and rooms on the website!\n";
