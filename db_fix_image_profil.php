<?php
try {
    $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=wannasni';
    $user = 'root';
    $password = '';
    
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Checking if image_profil column exists...\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `user` LIKE 'image_profil'");
    if ($stmt->fetch()) {
        echo "Column 'image_profil' ALREADY EXISTS.\n";
    } else {
        echo "Column 'image_profil' MISSING. Adding it...\n";
        $pdo->exec("ALTER TABLE `user` ADD COLUMN image_profil VARCHAR(255) DEFAULT NULL");
        echo "Column 'image_profil' ADDED SUCCESSFULLY.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
