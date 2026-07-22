<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM simcards WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Sim kart başarıyla silindi.';
    } else {
        $_SESSION['error'] = 'Sim kart silinirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz sim kart ID.';
}

header('Location: simcards.php');
exit;
?>