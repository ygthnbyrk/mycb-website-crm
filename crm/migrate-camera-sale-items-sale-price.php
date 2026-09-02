<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

$col = $conn->query("SHOW COLUMNS FROM camera_sale_items LIKE 'sale_price'")->fetch_assoc();
if ($col) {
    echo "OK: sale_price sütunu zaten var, bir şey yapılmadı.\n";
} else {
    if ($conn->query("ALTER TABLE camera_sale_items ADD COLUMN sale_price DECIMAL(10,2) NULL AFTER cost_price")) {
        echo "OK: camera_sale_items.sale_price sütunu eklendi.\n";
    } else {
        echo "HATA: " . $conn->error . "\n";
    }
}
?>
