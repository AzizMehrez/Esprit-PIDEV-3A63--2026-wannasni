<?php
/**
 * Clean Database Script
 * Removes all meal-related data from the database to allow clean ML testing
 */

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=wannasni', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Database Cleanup Script ===\n\n";
    
    // Get list of tables to clean
    $tables_to_clean = [
        'suivi_repas',           // Meal tracking
        'nutrition_journal',     // Nutrition logs
        'health_journal',        // Health journal entries
        'beverage_log',          // Beverage logs
        'rapport_hebdomadaire',  // Weekly reports
    ];
    
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    
    echo "Cleaning up meal data...\n";
    
    $total_deleted = 0;
    
    foreach ($tables_to_clean as $table) {
        // Check if table exists
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() === 0) {
            echo "  - Table '$table' does not exist, skipping...\n";
            continue;
        }
        
        // Count records before deletion
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$table`");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        if ($count > 0) {
            // Delete all records
            $deleteStmt = $pdo->prepare("TRUNCATE TABLE `$table`");
            $deleteStmt->execute();
            echo "  ✓ Cleaned '$table': removed $count records\n";
            $total_deleted += $count;
        } else {
            echo "  - '$table' is already empty\n";
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    
    echo "\n=== Results ===\n";
    echo "Total records deleted: $total_deleted\n";
    echo "Database is now clean for fresh ML testing!\n";
    echo "\n✓ Cleanup completed successfully\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
