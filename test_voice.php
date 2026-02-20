<?php
/**
 * Test script for voice assistant
 */

// Test French command for available activities
$projectDir = __DIR__;
$scriptPath = $projectDir . '/scripts/activity_assistant.py';

// Simulate the command
$message = 'les activites disponibles';
$userId = '1';  // Any user ID for testing
$lang = 'fr';

echo "Testing French command: '$message'\n";
echo "========================================\n\n";

// Run the Python script
$process = new \Symfony\Component\Process\Process(
    ['python', $scriptPath, $message, $userId, $lang],
    $projectDir
);
$process->setTimeout(30);

try {
    $process->mustRun();
    $output = $process->getOutput();
    $stderr = $process->getErrorOutput();
    
    echo "STDERR Output (debug logs):\n";
    echo $stderr;
    echo "\n========================================\n\n";
    
    echo "STDOUT Output (JSON result):\n";
    echo $output;
    echo "\n========================================\n\n";
    
    // Try to decode and parse
    $result = json_decode($output, true);
    if ($result) {
        echo "Parsed JSON:\n";
        echo "  text: " . ($result['text'] ?? 'NOT SET') . "\n";
        echo "  audio: " . ($result['audio'] ?? 'NOT SET') . "\n";
        echo "  intent: " . ($result['intent'] ?? 'NOT SET') . "\n";
        echo "  success: " . ($result['success'] ?? 'NOT SET') . "\n";
    } else {
        echo "Failed to parse JSON!\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "STDERR: " . $process->getErrorOutput() . "\n";
}
