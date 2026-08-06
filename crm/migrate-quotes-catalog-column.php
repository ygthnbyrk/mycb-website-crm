<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$col = $conn->query("SHOW COLUMNS FROM quotes LIKE 'catalog_images'")->fetch_assoc();
if ($col) {
    echo "OK: catalog_images sütunu zaten var, bir şey yapılmadı.\n";
} else {
    if ($conn->query("ALTER TABLE quotes ADD COLUMN catalog_images TEXT NULL AFTER notes")) {
        echo "OK: quotes.catalog_images sütunu eklendi.\n";
    } else {
        echo "HATA: " . $conn->error . "\n";
    }
}
?>
