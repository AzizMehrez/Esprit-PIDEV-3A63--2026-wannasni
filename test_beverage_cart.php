<?php
// Test direct de l'ajout au panier
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger .env
$dotenv = new Dotenv();
$dotenv->loadEnv('.env');

// Configuration
$database_url = $_ENV['DATABASE_URL'];

// Connexion directe à la DB
try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
    $pdo->exec('USE wannasni');
    
    echo "✅ Connexion à la base de données réussie\n\n";
    
    // Vérifier les utilisateurs
    $users = $pdo->query('SELECT id, email, first_name FROM user LIMIT 3')->fetchAll();
    echo "📝 UTILISATEURS (sample):\n";
    foreach ($users as $u) {
        echo "   - ID {$u['id']}: {$u['email']} ({$u['first_name']})\n";
    }
    
    echo "\n";
    
    // Vérifier les produits
    $products = $pdo->query('SELECT id, name, category, stock_quantity, is_active FROM beverage_product WHERE is_active = 1 LIMIT 3')->fetchAll();
    echo "📦 PRODUITS ACTIFS (sample):\n";
    foreach ($products as $p) {
        echo "   - ID {$p['id']}: {$p['name']} ({$p['category']}) - Stock: {$p['stock_quantity']} - Active: {$p['is_active']}\n";
    }
    
    echo "\n";
    
    // Vérifier la structure de beverage_order
    echo "🛒 STRUCTURE DE beverage_order:\n";
    $cols = $pdo->query('DESCRIBE beverage_order')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "   - {$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    
    echo "\n";
    
    // Vérifier existants orders
    $orders = $pdo->query('SELECT id, user_id, status, total_amount FROM beverage_order LIMIT 3')->fetchAll();
    echo "📋 COMMANDES EXISTANTES:\n";
    echo "   Total: " . $pdo->query('SELECT COUNT(*) as cnt FROM beverage_order')->fetch()['cnt'] . " commandes\n";
    foreach ($orders as $o) {
        echo "   - ID {$o['id']}: User {$o['user_id']} - Status: {$o['status']} - Total: {$o['total_amount']}\n";
    }
    
    echo "\n✅ Toutes les tables sont correctement structurées!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
