<?php
require_once __DIR__ . '/../config/config.php';

try {
    $res = db()->query("SELECT COUNT(*) as cnt FROM users WHERE role='owner'");
    $row = $res->fetch_assoc();
    echo "Owners: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM properties");
    $row = $res->fetch_assoc();
    echo "Properties: " . $row['cnt'] . "\n";
    
    $res = db()->query("SELECT COUNT(*) as cnt FROM rooms");
    $row = $res->fetch_assoc();
    echo "Rooms: " . $row['cnt'] . "\n";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
