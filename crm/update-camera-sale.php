<?php
require_once 'config.php';
require_once 'camera-sale-helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: camera-sales-list.php');
    exit;
}

$sale_id = intval($_POST['id'] ?? 0);
$customer_id = intval($_POST['customer_id'] ?? 0);
$sale_date = trim($_POST['sale_date'] ?? '') ?: date('Y-m-d');
$notes = trim($_POST['notes'] ?? '') ?: null;
$items = json_decode($_POST['items_data'] ?? '[]', true);

if (!$sale_id) {
    header('Location: camera-sales-list.php');
    exit;
}

if (!$customer_id) {
    $_SESSION['error'] = 'Lütfen müşteri seçin.';
    header('Location: edit-camera-sale.php?id=' . $sale_id);
    exit;
}

$check = $conn->prepare("SELECT id FROM camera_sales WHERE id = ?");
$check->bind_param('i', $sale_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $check->close();
    $_SESSION['error'] = 'Kamera satışı bulunamadı.';
    header('Location: camera-sales-list.php');
    exit;
}
$check->close();

// Eski kalemlere bağlı stok birimlerini geri Stokta'ya al, sonra eski kalem/abonelik
// kayıtlarını sil ki yeni kalem listesi sıfırdan ve doğru şekilde yazılabilsin.
revertCameraSaleStock($conn, $sale_id);
deleteCameraSaleItemsAndSubs($conn, $sale_id);

$stmt = $conn->prepare("UPDATE camera_sales SET sale_date = ?, customer_id = ?, notes = ? WHERE id = ?");
$stmt->bind_param('sisi', $sale_date, $customer_id, $notes, $sale_id);
$stmt->execute();
$stmt->close();

$shortage_notes = [];
if (!empty($items)) {
    $shortage_notes = saveCameraSaleItems($conn, $sale_id, $customer_id, $sale_date, $items);
}

$_SESSION['success'] = 'Kamera satışı güncellendi.' . (!empty($shortage_notes) ? ' UYARI: ' . implode(' ', $shortage_notes) : '');
header('Location: camera-sales-list.php');
exit;
