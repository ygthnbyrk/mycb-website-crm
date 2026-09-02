<?php
require_once 'config.php';
require_once 'camera-sale-helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create-camera-sale.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$customer_id = intval($_POST['customer_id'] ?? 0);
$sale_date = trim($_POST['sale_date'] ?? '') ?: date('Y-m-d');
$notes = trim($_POST['notes'] ?? '') ?: null;
$items = json_decode($_POST['items_data'] ?? '[]', true);

if (!$customer_id) {
    $_SESSION['error'] = 'Lütfen müşteri seçin.';
    header('Location: create-camera-sale.php');
    exit;
}

$stmt = $conn->prepare("INSERT INTO camera_sales (sale_date, customer_id, notes, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param('sisi', $sale_date, $customer_id, $notes, $user_id);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Kamera satışı kaydedilirken hata oluştu.';
    header('Location: create-camera-sale.php');
    exit;
}
$camera_sale_id = $conn->insert_id;
$stmt->close();

$shortage_notes = [];
if (!empty($items)) {
    $shortage_notes = saveCameraSaleItems($conn, $camera_sale_id, $customer_id, $sale_date, $items);
}

$_SESSION['success'] = 'Kamera satışı kaydedildi.' . (!empty($shortage_notes) ? ' UYARI: ' . implode(' ', $shortage_notes) : '');
header('Location: camera-sales-list.php');
exit;
