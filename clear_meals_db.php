<?php
/**
 * Clear Meals Database
 * Vide la table des repas pour recommencer à zéro
 */

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'wannasni';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Count before
    $before = $pdo->query("SELECT COUNT(*) FROM suivi_repas")->fetchColumn();
    echo "[*] Repas avant: $before\n";

    // Clear meals
    $stmt = $pdo->query("DELETE FROM suivi_repas");
    $deleted = $stmt->rowCount();
    
    echo "[+] $deleted repas supprimes\n";

    // Verify
    $after = $pdo->query("SELECT COUNT(*) FROM suivi_repas")->fetchColumn();
    echo "[OK] Repas maintenant: $after\n";

    // Optional: Clear meal_items if exists
    $tables = $pdo->query("SHOW TABLES LIKE 'meal_items'")->fetchAll();
    if (!empty($tables)) {
        $rows = $pdo->query("DELETE FROM meal_items")->rowCount();
        echo "[+] $rows meal_items supprimees\n";
    }

    // Optional: Clear meal_analysis if exists  
    $tables2 = $pdo->query("SHOW TABLES LIKE 'meal_analysis'")->fetchAll();
    if (!empty($tables2)) {
        $rows2 = $pdo->query("DELETE FROM meal_analysis")->rowCount();
        echo "[+] $rows2 meal_analysis supprimees\n";
    }

    echo "\n[SUCCESS] Base de donnees videe avec succes!\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
