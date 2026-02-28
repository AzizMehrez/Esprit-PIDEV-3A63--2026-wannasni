<?php
/**
 * Database Configuration for XAMPP
 * Update these settings to match your XAMPP MySQL setup
 */

// CORS Headers - Allow requests from your frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Default XAMPP username
define('DB_PASS', '');               // Default XAMPP password (empty)
define('DB_NAME', 'wannasni');       // Your database name

/**
 * Get database connection
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Close database connection
 */
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Sanitize input to prevent SQL injection
 */
function sanitizeInput($conn, $input) {
    return $conn->real_escape_string(trim($input));
}
?>
