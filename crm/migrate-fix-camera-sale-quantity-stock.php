<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Lütfen giriş yapın");
}

// Bu script SADECE geçmişte kamera satışına konu olmuş ama adet>1 yüzünden eksik
// işaretlenmiş ürünleri düzeltir. "Stokta" olan HER ürünü satıldı yapmaz — hiç
// satılmamış gerçek stok ürünlerine dokunmaz.
//
// Sorun: quantity>1 girilen eski satır tek bir product_id'ye bağlıydı; sadece o ürün
// "Satıldı" oluyordu, aynı satıştaki diğer (quantity-1) birim hâlâ "Stokta" görünüyordu.
// Bu script her böyle satır için, o satırın kaydettiği quantity kadar aynı modelden
// "Stokta" birimi bulup hepsini "Satıldı" yapar.

$rows_stmt = $conn->query(
    "SELECT csi.id, csi.product_id, csi.quantity
     FROM camera_sale_items csi
     WHERE csi.product_id IS NOT NULL AND csi.quantity > 1"
);

$fixed_total = 0;
$rows_checked = 0;

while ($row = $rows_stmt->fetch_assoc()) {
    $rows_checked++;
    $product_id = (int)$row['product_id'];
    $quantity = (int)$row['quantity'];

    $model_stmt = $conn->prepare("SELECT model FROM products WHERE id = ?");
    $model_stmt->bind_param('i', $product_id);
    $model_stmt->execute();
    $model_row = $model_stmt->get_result()->fetch_assoc();
    $model_stmt->close();

    if (!$model_row) {
        continue;
    }

    // Ana ürünün kendisi zaten Satıldı olmalı (önceki migration'da yapıldı), garantiye alalım.
    $anchor_stmt = $conn->prepare("UPDATE products SET status = 'Satıldı' WHERE id = ? AND status != 'Satıldı'");
    $anchor_stmt->bind_param('i', $product_id);
    $anchor_stmt->execute();
    $anchor_stmt->close();

    $need = $quantity - 1;
    $extra_stmt = $conn->prepare(
        "UPDATE products SET status = 'Satıldı'
         WHERE model = ? AND status = 'Stokta' AND id != ?
         ORDER BY id ASC LIMIT ?"
    );
    $extra_stmt->bind_param('sii', $model_row['model'], $product_id, $need);
    $extra_stmt->execute();
    $fixed_total += $extra_stmt->affected_rows;
    $extra_stmt->close();
}

echo "OK: $rows_checked adet>1 satış kalemi kontrol edildi, $fixed_total ürün ek olarak 'Satıldı' yapıldı.\n";
?>
