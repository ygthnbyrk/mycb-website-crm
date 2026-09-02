<?php
require_once 'config.php';
require_once 'camera-sale-helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$sale_id = intval($_GET['id'] ?? 0);

if ($sale_id > 0) {
    revertCameraSaleStock($conn, $sale_id);
    deleteCameraSaleItemsAndSubs($conn, $sale_id);

    $stmt = $conn->prepare("DELETE FROM camera_sales WHERE id = ?");
    $stmt->bind_param('i', $sale_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Kamera satışı silindi. Bağlı ürünler stoğa geri eklendi.';
    } else {
        $_SESSION['error'] = 'Kamera satışı silinirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz satış ID.';
}

header('Location: camera-sales-list.php');
exit;
