#!/usr/bin/env php
<?php
/**
 * Test script to verify add-to-cart endpoint works with proper authentication
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

// Load .env
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

// Create kernel
$kernel = new \App\Kernel($_ENV['APP_ENV'] ?? 'dev', $_ENV['APP_DEBUG'] ?? false);
$kernel->boot();

// Create test client
$client = new Client($kernel);

// First, get the login page to establish session
echo "1️⃣  Getting login page...\n";
$crawler = $client->request('GET', '/fr/login');
echo "Status: " . $client->getResponse()->getStatusCode() . "\n";

// Extract CSRF token from login form
$csrfToken = $crawler->selectButton('btn btn-primary btn-large btn-full')->form()->get('_csrf_token')->getValue();
echo "CSRF token from login form: " . substr($csrfToken, 0, 20) . "...\n\n";

// Login with test user
echo "2️⃣  Logging in...\n";
$client->request('POST', '/fr/login', [
    '_username' => 'test@test.com',
    '_password' => 'password123',
    '_csrf_token' => $csrfToken
]);
$statusCode = $client->getResponse()->getStatusCode();
echo "Login response: $statusCode\n";
if ($statusCode == 302) {
    echo "✅ Login redirect received (session established)\n";
} else {
    echo "Response:\n" . substr($client->getResponse()->getContent(), 0, 200) . "\n";
}

// Now test the add-to-cart endpoint
echo "\n3️⃣  Testing add-to-cart endpoint...\n";
$client->request('POST', '/fr/nutrition/sommelier/marketplace/add-to-cart', [
    'product_id' => '1',
    'quantity' => '1'
], [], [
    'HTTP_X_CSRF_TOKEN' => 'test-token',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);

$response = $client->getResponse();
$statusCode = $response->getStatusCode();
$contentType = $response->headers->get('Content-Type');
$content = $response->getContent();

echo "Status: $statusCode\n";
echo "Content-Type: $contentType\n";
echo "Response (first 500 chars):\n";
echo substr($content, 0, 500) . "\n\n";

// Try to parse as JSON
if (strpos($contentType, 'application/json') !== false) {
    $json = json_decode($content, true);
    echo "✅ Valid JSON response\n";
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "❌ Not JSON - probably HTML (login page?)\n";
}

$kernel->shutdown();
?>
