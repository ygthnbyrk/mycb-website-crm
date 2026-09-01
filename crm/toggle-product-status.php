<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

// Toggle öncesi hangi filtre/sayfadaydıysa oraya geri dönmek için
$return = $_GET['return'] ?? '';
if ($return !== '' && !preg_match('/^[A-Za-z0-9=&%.+_\-]*$/', $return)) {
    $return = '';
}
$allowed_redirects = ['products.php', 'teknoloji-urunler.php'];
$base_page = in_array($_GET['from'] ?? '', $allowed_redirects, true) ? $_GET['from'] : 'products.php';
$redirect = $base_page . ($return !== '' ? '?' . $return : '');

// Bu hızlı işlem sadece Stokta <-> Pasif geçişi içindir; satış kaydı gerektiren
// "Satıldı" durumu buradan değil, satış akışından yönetilir.
if ($id > 0 && in_array($status, ['Stokta', 'Pasif'], true)) {
    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ? AND status != 'Satıldı'");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = $status === 'Pasif' ? 'Ürün pasife alındı.' : 'Ürün stoğa alındı.';
    } else {
        $_SESSION['error'] = 'Durum güncellenirken hata oluştu.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Geçersiz istek.';
}

header('Location: ' . $redirect);
exit;
?>
