<?php
// Diagnose CSRF token generation

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

// Create minimal Symfony kernel to test CSRF
$kernel_class = 'App\\Kernel';
require_once __DIR__.'/src/Kernel.php';

$kernel = new $kernel_class($_ENV['APP_ENV'] ?? 'dev', $_ENV['APP_DEBUG'] ?? false);
$kernel->boot();

$container = $kernel->getContainer();

// Get CSRF token manager
try {
    $tokenManager = $container->get('security.csrf.token_manager');
    
    echo "✅ CSRF Token Manager found\n\n";
    
    // Try different token IDs
    $testIds = ['marketplace', 'sommelier', 'app', 'authenticate', 'form'];
    
    foreach ($testIds as $id) {
        try {
            $token = $tokenManager->getToken($id);
            echo "[$id] => " . ($token ? substr($token->getValue(), 0, 30) . '...' : 'NULL') . "\n";
        } catch (\Exception $e) {
            echo "[$id] => ERROR: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
?>
