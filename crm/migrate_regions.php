<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

foreach (['il', 'ilce'] as $col) {
    $check = $conn->query("SHOW COLUMNS FROM customers LIKE '$col'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN $col VARCHAR(100) NULL");
        echo "$col kolonu eklendi.\n";
    } else {
        echo "$col kolonu zaten vardi.\n";
    }
}

$map = json_decode(file_get_contents(__DIR__ . '/region_map.json'), true);
$by_tax = $map['by_tax'];
$by_name = $map['by_name'];

$res = $conn->query("SELECT id, name, tax_number, il, ilce FROM customers");
$updated = 0;
$skipped_has_region = 0;
$no_match = 0;

$stmt = $conn->prepare("UPDATE customers SET il = ?, ilce = ? WHERE id = ?");

while ($c = $res->fetch_assoc()) {
    if (!empty($c['il']) || !empty($c['ilce'])) {
        $skipped_has_region++;
        continue;
    }
    $tax = trim($c['tax_number']);
    $name = strtoupper(trim($c['name']));
    $entry = null;
    if ($tax && isset($by_tax[$tax])) {
        $entry = $by_tax[$tax];
    } elseif (isset($by_name[$name])) {
        $entry = $by_name[$name];
    }
    if ($entry === null) {
        $no_match++;
        continue;
    }
    $il = $entry['il'];
    $ilce = $entry['ilce'];
    $stmt->bind_param("ssi", $il, $ilce, $c['id']);
    $stmt->execute();
    $updated++;
}

echo "\nGuncellenen musteri: $updated\n";
echo "Zaten bolgesi olan (atlandi): $skipped_has_region\n";
echo "Eslesme bulunamadi: $no_match\n";
