<?php
// Quick test to verify fetch credentials work with session

session_start();

echo "Session ID: " . session_id() . "\n";
echo "User Session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "Not set") . "\n";

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo "✅ AJAX request detected\n";
} else {
    echo "❌ Not an AJAX request\n";
}

// Check headers
echo "\n=== REQUEST HEADERS ===\n";
echo "X-CSRF-Token: " . ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'Not sent') . "\n";
echo "X-Requested-With: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'Not sent') . "\n";

// Test credentials
echo "\n=== CREDENTIALS TEST ===\n";
echo "POST vars: " . json_encode($_POST) . "\n";
echo "Cookies: " . json_encode($_COOKIE) . "\n";
?>
