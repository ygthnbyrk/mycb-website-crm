<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Müşteri başarıyla silindi.';
    } else {
        $_SESSION['error'] = 'Hata: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz ID.';
}

header('Location: customers.php');
exit;
?>