<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== subscriptions kolonlari ===\n";
$cols = $conn->query("SHOW COLUMNS FROM subscriptions");
while ($c = $cols->fetch_assoc()) {
    echo $c['Field'] . " | " . $c['Type'] . "\n";
}

echo "\n=== ornek 10 kayit ===\n";
$res = $conn->query("SELECT * FROM subscriptions LIMIT 10");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== toplam kayit sayisi ===\n";
echo $conn->query("SELECT COUNT(*) c FROM subscriptions")->fetch_assoc()['c'] . "\n";

echo "\n=== item_type dagilimi ===\n";
$res = $conn->query("SELECT item_type, COUNT(*) c FROM subscriptions GROUP BY item_type");
while ($r = $res->fetch_assoc()) echo $r['item_type'] . ': ' . $r['c'] . "\n";
