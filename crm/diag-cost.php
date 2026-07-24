<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$res = $conn->query("SELECT model, COUNT(*) c, AVG(cost_price) avg_cost, SUM(cost_price) sum_cost FROM products GROUP BY model ORDER BY c DESC");
while ($r = $res->fetch_assoc()) {
    echo $r['model'] . " | adet: " . $r['c'] . " | ort.maliyet: " . $r['avg_cost'] . " | toplam.maliyet: " . $r['sum_cost'] . "\n";
}
