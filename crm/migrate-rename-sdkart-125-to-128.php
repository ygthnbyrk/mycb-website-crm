<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$stmt = $conn->prepare("UPDATE products SET model = 'SD Kart 128 GB' WHERE model = 'SD Kart 125 GB'");
if ($stmt->execute()) {
    echo "OK: " . $stmt->affected_rows . " ürün kaydında model 'SD Kart 125 GB' -> 'SD Kart 128 GB' olarak güncellendi.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
$stmt->close();
?>
