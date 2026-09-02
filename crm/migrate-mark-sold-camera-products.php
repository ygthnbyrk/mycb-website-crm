<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

// Kamera satışı kalemlerinde geçen ama henüz "Satıldı" işaretlenmemiş ürünleri düzeltir
// (save-camera-sale.php artık yeni satışlarda bunu otomatik yapıyor; bu script geçmiş kayıtlar içindir).
$sql = "UPDATE products p
        JOIN camera_sale_items csi ON csi.product_id = p.id
        SET p.status = 'Satıldı'
        WHERE p.status != 'Satıldı'";

if ($conn->query($sql)) {
    echo "OK: " . $conn->affected_rows . " ürün 'Satıldı' olarak güncellendi.\n";
} else {
    echo "HATA: " . $conn->error . "\n";
}
?>
