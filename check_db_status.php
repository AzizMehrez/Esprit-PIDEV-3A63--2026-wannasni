<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
    
    // Check if database exists
    $result = $pdo->query('SHOW DATABASES LIKE "wannasni"')->fetch();
    
    if ($result) {
        echo "✅ Database 'wannasni' EXISTS\n\n";
        
        // Check tables
        $pdo->exec('USE wannasni');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        
        echo "📊 Total tables: " . count($tables) . "\n\n";
        
        // List all tables
        foreach ($tables as $table) {
            echo "  ✓ $table\n";
        }
        
        // Check for beverage tables specifically
        echo "\n🍷 BEVERAGE TABLES STATUS:\n";
        $beverageTables = ['beverage_order', 'beverage_order_item', 'beverage_product', 'beverage_log', 'beverage'];
        foreach ($beverageTables as $table) {
            if (in_array($table, $tables)) {
                echo "  ✅ $table\n";
            } else {
                echo "  ❌ $table - MISSING\n";
            }
        }
        
    } else {
        echo "❌ Database 'wannasni' NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
