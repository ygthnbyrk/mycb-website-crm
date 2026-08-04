<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$before = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->fetch_assoc();
echo "Önce: " . $before['Type'] . "\n";

$sql = "ALTER TABLE products MODIFY status ENUM('Stokta','Pasif','Satıldı') NOT NULL DEFAULT 'Stokta'";

if ($conn->query($sql)) {
    $after = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->fetch_assoc();
    echo "Sonra: " . $after['Type'] . "\n";
    echo "OK: products.status enum'una 'Pasif' eklendi.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
?>
