<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

foreach (['sales', 'sale_products', 'sale_simcards', 'customers', 'products'] as $t) {
    echo "=== $t ===\n";
    $cols = $conn->query("SHOW COLUMNS FROM $t");
    while ($c = $cols->fetch_assoc()) {
        echo $c['Field'] . " | " . $c['Type'] . "\n";
    }
    echo "\n";
}
