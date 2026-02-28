<?php
/**
 * Chat Proxy - Securely handles API requests
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load configuration
require_once 'api_config.php';

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Prepare the request to the AI provider
$data = [
    'model' => AI_MODEL,
    'messages' => $input['messages'],
    'temperature' => 0.7,
    'max_tokens' => 2048
];

// Initialize cURL
$ch = curl_init(AI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . AI_API_KEY
]);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Request Error: ' . curl_error($ch)]);
} else {
    http_response_code($httpCode);
    echo $response;
}

curl_close($ch);
?>
