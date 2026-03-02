<?php
require_once __DIR__.'/vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? null;
$token = $_ENV['TWILIO_AUTH_TOKEN'] ?? null;
$from = $_ENV['TWILIO_FROM_NUMBER'] ?? null;
$to = '+21650933019';
$message = 'Test Twilio Direct Script';

$out = "SID: $sid\nFrom: $from\nTo: $to\n";

if (!$sid || !$token || !$from) {
    file_put_contents('debug_twilio.log', $out . "Missing credentials\n");
    exit(1);
}

$client = HttpClient::create();
try {
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $response = $client->request('POST', $url, [
        'auth_basic' => [$sid, $token],
        'body' => [
            'To' => $to,
            'From' => $from,
            'Body' => $message,
        ],
    ]);

    $out .= "Status Code: " . $response->getStatusCode() . "\n";
    $out .= print_r($response->toArray(false), true);
    file_put_contents('debug_twilio.log', $out);
} catch (\Exception $e) {
    file_put_contents('debug_twilio.log', $out . "Error: " . $e->getMessage() . "\n");
}
