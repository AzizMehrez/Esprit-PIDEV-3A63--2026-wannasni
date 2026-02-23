<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wannasni','root','');
echo "=== ORDERS ===\n";
$rows = $pdo->query('SELECT * FROM beverage_order ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { print_r($r); }

echo "\n=== ORDER ITEM COLUMNS ===\n";
$cols = $pdo->query('DESCRIBE beverage_order_item')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) { echo $c['Field'] . ' (' . $c['Type'] . ")\n"; }

echo "\n=== ORDER ITEMS ===\n";
$items = $pdo->query('SELECT * FROM beverage_order_item ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as $i) { print_r($i); }
