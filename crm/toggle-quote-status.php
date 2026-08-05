<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

$return = $_GET['return'] ?? '';
if ($return !== '' && !preg_match('/^[A-Za-z0-9=&%.+_\-]*$/', $return)) {
    $return = '';
}
$redirect = 'quotes.php' . ($return !== '' ? '?' . $return : '');

if ($id > 0 && in_array($status, ['Beklemede', 'Olumlu', 'Olumsuz'], true)) {
    $stmt = $conn->prepare("UPDATE quotes SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Teklif durumu güncellendi.';
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
