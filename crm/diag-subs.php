<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

$serials = json_decode(file_get_contents(__DIR__ . '/yenileme_serials.json'), true);
$serials = array_unique($serials);
echo "Excel'deki benzersiz seri no sayisi: " . count($serials) . "\n\n";

$matched = 0;
$unmatched = [];
$multi = [];
foreach ($serials as $s) {
    $stmt = $conn->prepare("SELECT id, status, item_type FROM subscriptions WHERE item_detail = ? AND item_type = 'product'");
    $stmt->bind_param("s", $s);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $unmatched[] = $s;
    } else {
        $matched++;
        if ($res->num_rows > 1) $multi[] = $s;
    }
}
echo "Eslesen: $matched\n";
echo "Eslesmeyen: " . count($unmatched) . "\n";
echo "Birden fazla eslesen: " . count($multi) . "\n\n";
echo "Eslesmeyen ornekler (ilk 20):\n";
foreach (array_slice($unmatched, 0, 20) as $u) echo " - $u\n";

echo "\nDurum dagilimi (eslesenler icin):\n";
$status_counts = [];
foreach ($serials as $s) {
    $stmt = $conn->prepare("SELECT status FROM subscriptions WHERE item_detail = ? AND item_type = 'product' LIMIT 1");
    $stmt->bind_param("s", $s);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) {
        $status_counts[$r['status']] = ($status_counts[$r['status']] ?? 0) + 1;
    }
}
foreach ($status_counts as $k => $v) echo "$k: $v\n";
