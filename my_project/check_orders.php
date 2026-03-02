<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wannasni', 'root', '');
$stmt = $pdo->query("SELECT bo.id, bo.status, bo.order_number, COUNT(boi.id) as item_count 
    FROM beverage_order bo 
    LEFT JOIN beverage_order_item boi ON bo.id = boi.beverage_order_id 
    WHERE bo.status != 'cart' AND bo.user_id = 1 
    GROUP BY bo.id 
    ORDER BY bo.created_at DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " non-cart orders for user 1:\n";
foreach ($rows as $r) {
    print_r($r);
}
