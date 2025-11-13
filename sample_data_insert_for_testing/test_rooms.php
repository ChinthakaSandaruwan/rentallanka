<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_POST = [
    'count' => 3,
    'owner_id' => 2,
    'status' => 'available'
];

include __DIR__ . '/insert_rooms.php';
