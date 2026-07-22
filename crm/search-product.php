<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Oturum yok']);
    exit;
}

$imei = $_GET['imei'] ?? '';

if (empty($imei)) {
    echo json_encode(['error' => 'IMEI numarası girin']);
    exit;
}

$stmt = $conn->prepare("SELECT id, imei_number, model, total_cost, status FROM products WHERE imei_number = ?");
$stmt->bind_param("s", $imei);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    
    if ($product['status'] === 'Satıldı') {
        echo json_encode(['error' => 'Bu ürün zaten satılmış']);
    } else {
        echo json_encode($product, JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(['error' => 'Ürün bulunamadı']);
}

$stmt->close();
?>