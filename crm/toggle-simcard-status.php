<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

// Bu hızlı işlem sadece Stokta <-> Pasif geçişi içindir; satış kaydı gerektiren
// "Satıldı" durumu buradan değil, satış akışından yönetilir.
if ($id > 0 && in_array($status, ['Stokta', 'Pasif'], true)) {
    $stmt = $conn->prepare("UPDATE simcards SET status = ? WHERE id = ? AND status != 'Satıldı'");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = $status === 'Pasif' ? 'Sim kart pasife alındı.' : 'Sim kart stoğa alındı.';
    } else {
        $_SESSION['error'] = 'Durum güncellenirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz istek.';
}

header('Location: simcards.php');
exit;
?>
