<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Toplam musteri: " . $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] . "\n";
echo "Bolgesi olan: " . $conn->query("SELECT COUNT(*) c FROM customers WHERE il IS NOT NULL AND il != ''")->fetch_assoc()['c'] . "\n\n";

echo "=== created_at dagilimi (tarih bazinda ilk 20) ===\n";
$res = $conn->query("SELECT DATE(created_at) d, COUNT(*) c FROM customers GROUP BY d ORDER BY d LIMIT 20");
while ($r = $res->fetch_assoc()) echo $r['d'] . " | " . $r['c'] . "\n";

echo "\n=== bolgesiz ornek 15 musteri ===\n";
$res = $conn->query("SELECT id, name, tax_number, created_at FROM customers WHERE il IS NULL OR il = '' ORDER BY id LIMIT 15");
while ($r = $res->fetch_assoc()) echo $r['id'] . " | [" . $r['name'] . "] | [" . $r['tax_number'] . "] | " . $r['created_at'] . "\n";
