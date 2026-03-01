<?php
try {
    $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=wannasni';
    $user = 'root';
    $password = '';
    
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO user (email, roles, password, status, created_at, disponible) VALUES ('admin@wannasni.com', '[\"ROLE_ADMIN\"]', '$2y$10$V2QsYLJxM5Gax1fqRAs8LkQDMGwaNhwO', 'active', NOW(), 1)";
    $pdo->exec($sql);
    echo "SUCCESS: User inserted.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "User already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
