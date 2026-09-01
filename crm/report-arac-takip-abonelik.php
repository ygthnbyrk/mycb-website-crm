<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

header('Content-Type: text/plain; charset=utf-8');

$min = isset($_GET['min']) ? (int)$_GET['min'] : 5;

$sql = "SELECT c.name, c.tax_number, c.phone, c.address,
               COUNT(*) as arac_sayisi
        FROM subscriptions s
        JOIN customers c ON s.customer_id = c.id
        WHERE s.item_type = 'product' AND s.status != 'İptal'
        GROUP BY c.id, c.name, c.tax_number, c.phone, c.address
        HAVING COUNT(*) >= ?
        ORDER BY arac_sayisi DESC, c.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $min);
$stmt->execute();
$result = $stmt->get_result();

$i = 1;
while ($row = $result->fetch_assoc()) {
    echo $i++ . "\t" . $row['name'] . "\t" . $row['tax_number'] . "\t" . ($row['phone'] ?? '') . "\t" . ($row['address'] ?? '') . "\t" . $row['arac_sayisi'] . "\n";
}
