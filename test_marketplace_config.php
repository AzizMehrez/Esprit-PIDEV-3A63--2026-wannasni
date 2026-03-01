<?php
/**
 * Quick test for beverage marketplace issues
 */

// Simulate HTTP request testing
echo "=== Test Beverage Marketplace ===" . PHP_EOL;
echo "1. Photo Upload Service: Checking if beverages directory exists..." . PHP_EOL;

$bevDir = __DIR__ . '/public/uploads/beverages';
if (is_dir($bevDir)) {
    echo "   ✅ Beverages directory exists: $bevDir" . PHP_EOL;
    echo "   Files: " . count(scandir($bevDir)) . " items" . PHP_EOL;
} else {
    echo "   ❌ Beverages directory NOT found!" . PHP_EOL;
}

echo PHP_EOL . "2. Checking BeverageOrder Entity..." . PHP_EOL;
require_once __DIR__ . '/src/Entity/BeverageOrder.php';
if (class_exists('App\Entity\BeverageOrder')) {
    echo "   ✅ BeverageOrder Entity found" . PHP_EOL;
    echo "   STATUS_CART = 'cart'" . PHP_EOL;
    echo "   STATUS_CONFIRMED = 'confirmed'" . PHP_EOL;
} else {
    echo "   ❌ BeverageOrder Entity NOT found!" . PHP_EOL;
}

echo PHP_EOL . "3. Configuration Check..." . PHP_EOL;
$config_path = __DIR__ . '/config/services.yaml';
if (file_exists($config_path)) {
    $content = file_get_contents($config_path);
    if (strpos($content, 'beverage_photos_directory') !== false) {
        echo "   ✅ beverage_photos_directory configured" . PHP_EOL;
    } else {
        echo "   ❌ beverage_photos_directory NOT in configuration!" . PHP_EOL;
    }
} else {
    echo "   ❌ services.yaml NOT found!" . PHP_EOL;
}

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;
