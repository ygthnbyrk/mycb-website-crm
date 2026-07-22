<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Ürün başarıyla silindi.';
    } else {
        $_SESSION['error'] = 'Ürün silinirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz ürün ID.';
}

header('Location: products.php');
exit;
?>