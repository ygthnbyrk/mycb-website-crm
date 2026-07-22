<?php
require_once 'config.php';
require_once 'SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT name, tax_number, email, phone, address, created_at FROM customers ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();

$data = [
    ['<b>Müşteri Adı</b>', '<b>Vergi Numarası</b>', '<b>Email</b>', '<b>Telefon</b>', '<b>Adres</b>', '<b>Kayıt Tarihi</b>']
];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        $row['name'],
        $row['tax_number'],
        $row['email'] ?? '',
        $row['phone'] ?? '',
        $row['address'] ?? '',
        $row['created_at']
    ];
}

$filename = 'musteriler_' . date('Y-m-d_H-i-s') . '.xlsx';

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);

$stmt->close();
$conn->close();
exit;
?>