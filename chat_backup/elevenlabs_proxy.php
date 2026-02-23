<?php
/**
 * ElevenLabs Text-to-Speech Proxy
 * Securely calls ElevenLabs API server-side and returns MP3 audio
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ============================================
// CONFIGURATION — Replace with your API key
// ============================================
$ELEVENLABS_API_KEY = "YOUR_API_KEY";
$VOICE_ID = "JBFqnCBsd6RMkjVDRZzb";
$MODEL_ID = "eleven_multilingual_v2";
$OUTPUT_FORMAT = "mp3_44100_128";

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['text']) || empty(trim($input['text']))) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing or empty "text" field']);
    exit;
}

$text = trim($input['text']);

// Limit text length to save API credits
if (strlen($text) > 1000) {
    $text = substr($text, 0, 1000);
}

// ElevenLabs API endpoint
$url = "https://api.elevenlabs.io/v1/text-to-speech/{$VOICE_ID}?output_format={$OUTPUT_FORMAT}";

// Request body
$postData = json_encode([
    'text' => $text,
    'model_id' => $MODEL_ID,
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.75,
        'style' => 0.0,
        'use_speaker_boost' => true
    ]
]);

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'xi-api-key: ' . $ELEVENLABS_API_KEY
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if (curl_errno($ch)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($httpCode !== 200) {
    // Forward the error from ElevenLabs
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ElevenLabs API error', 'status' => $httpCode, 'details' => $response]);
    exit;
}

// Success — return the MP3 audio
header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($response));
echo $response;
?>
