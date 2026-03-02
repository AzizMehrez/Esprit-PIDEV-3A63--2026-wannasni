<?php
try {
    $dsn = 'mysql:host=127.0.0.1;port=3306';
    $user = 'root';
    $password = '';
    
    echo "Attempting connection to $dsn with user $user...\n";
    $pdo = new PDO($dsn, $user, $password);
    echo "Connection OK!\n";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'wannasni'");
    if ($stmt->fetch()) {
        echo "Database 'wannasni' exists.\n";
    } else {
        echo "Database 'wannasni' does NOT exist.\n";
    }
} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage() . "\n";
    exit(1);
}
