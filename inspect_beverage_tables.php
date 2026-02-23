<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
    $pdo->exec('USE wannasni');
    
    echo "🔍 BEVERAGE TABLE INSPECTION\n";
    echo "═══════════════════════════════════════\n\n";
    
    // Check beverage_product
    $count = $pdo->query('SELECT COUNT(*) as cnt FROM beverage_product')->fetch()['cnt'];
    echo "📦 beverage_product: $count rows\n";
    
    $products = $pdo->query('SELECT id, name, category, is_active FROM beverage_product LIMIT 3')->fetchAll();
    if ($products) {
        foreach ($products as $p) {
            echo "   - ID {$p['id']}: {$p['name']} ({$p['category']}) - Active: {$p['is_active']}\n";
        }
    } else {
        echo "   ❌ No products found!\n";
    }
    
    echo "\n";
    
    // Check beverage_order
    $count = $pdo->query('SELECT COUNT(*) as cnt FROM beverage_order')->fetch()['cnt'];
    echo "📋 beverage_order: $count rows\n";
    
    // Check structure of beverage_order
    $cols = $pdo->query('DESCRIBE beverage_order')->fetchAll(PDO::FETCH_ASSOC);
    echo "   Columns:\n";
    foreach ($cols as $col) {
        echo "   - {$col['Field']}: {$col['Type']}\n";
    }
    
    echo "\n";
    
    // Check beverage_order_item
    $count = $pdo->query('SELECT COUNT(*) as cnt FROM beverage_order_item')->fetch()['cnt'];
    echo "🛒 beverage_order_item: $count rows\n";
    
    // Check structure
    $cols = $pdo->query('DESCRIBE beverage_order_item')->fetchAll(PDO::FETCH_ASSOC);
    echo "   Columns:\n";
    foreach ($cols as $col) {
        echo "   - {$col['Field']}: {$col['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
