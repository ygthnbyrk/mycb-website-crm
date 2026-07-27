<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$test_serials = ['861100065083020', '866897050418773', '862843049735202'];

foreach ($test_serials as $s) {
    echo "=== $s ===\n";
    $stmt = $conn->prepare("SELECT id, item_detail, item_type FROM subscriptions WHERE item_detail = ?");
    $stmt->bind_param("s", $s);
    $stmt->execute();
    $res = $stmt->get_result();
    echo "subscriptions eslesme: " . $res->num_rows . "\n";
    while ($r = $res->fetch_assoc()) echo "  " . json_encode($r) . "\n";

    $stmt2 = $conn->prepare("SELECT id, imei_number, model, status, customer_id FROM products WHERE imei_number = ?");
    $stmt2->bind_param("s", $s);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    echo "products eslesme: " . $res2->num_rows . "\n";
    while ($r = $res2->fetch_assoc()) echo "  " . json_encode($r) . "\n";
    echo "\n";
}

echo "Ornek item_detail degerleri (subscriptions, product tipi):\n";
$res = $conn->query("SELECT item_detail FROM subscriptions WHERE item_type='product' LIMIT 5");
while ($r = $res->fetch_assoc()) echo " [" . $r['item_detail'] . "]\n";
